<?php
declare(strict_types=1);

namespace Blocky\Core\Tokens;

/**
 * Stores runtime theme token overrides edited by the Blocky builder.
 */
final class ThemeSettings
{
    private const OPTION = 'blocky_theme_token_overrides';
    private const TOKENS_DIR = BLOCKY_CORE_DIR . 'resources/tokens/';

    /** @return array<string, string> */
    public static function baseDefaults(): array
    {
        $path = self::TOKENS_DIR . 'tokens.php';
        if (!\file_exists($path)) {
            return [];
        }

        $tokens = require $path;
        if (!\is_array($tokens)) {
            return [];
        }

        $result = [];
        foreach ($tokens as $key => $value) {
            if (\is_string($key) && (\is_string($value) || \is_numeric($value))) {
                $result[$key] = (string) $value;
            }
        }

        return $result;
    }

    /** @return array<string, string> */
    public static function darkDefaults(): array
    {
        $path = self::TOKENS_DIR . 'tokens.dark.css';
        if (!\file_exists($path)) {
            return [];
        }

        $css = \file_get_contents($path);
        if (!\is_string($css)) {
            return [];
        }

        if (!\preg_match('/:\\s*root\\[data-mode="dark"\\]\\s*\\{(?P<body>.*?)\\}/s', $css, $match)) {
            return [];
        }

        $result = [];
        if (\preg_match_all('/--bky-([a-zA-Z0-9-]+)\\s*:\\s*([^;]+);/', (string) $match['body'], $declarations, \PREG_SET_ORDER)) {
            foreach ($declarations as $declaration) {
                $result[(string) $declaration[1]] = \trim((string) $declaration[2]);
            }
        }

        return $result;
    }

    /** @return array<string, array<string, string>> */
    public static function overrides(): array
    {
        $raw = \get_option(self::OPTION, []);
        if (!\is_array($raw)) {
            return [];
        }

        return self::sanitizeOverrides($raw);
    }

    /** @param array<string, mixed> $overrides @return array<string, array<string, string>> */
    public static function saveOverrides(array $overrides): array
    {
        $sanitized = self::sanitizeOverrides($overrides);
        \update_option(self::OPTION, $sanitized, false);

        return $sanitized;
    }

    /** @return array<string, mixed> */
    public static function settingsForRest(): array
    {
        $baseDefaults = self::baseDefaults();
        $darkDefaults = self::darkDefaults();
        $overrides = self::overrides();

        return [
            'groups' => self::groups(),
            'variants' => [
                'default--light' => [
                    'id' => 'default--light',
                    'label' => \__('Default Light', 'blocky'),
                    'brand' => 'default',
                    'mode' => 'light',
                    'defaults' => $baseDefaults,
                    'overrides' => $overrides['default--light'] ?? [],
                    'values' => \array_merge($baseDefaults, $overrides['default--light'] ?? []),
                ],
                'default--dark' => [
                    'id' => 'default--dark',
                    'label' => \__('Default Dark', 'blocky'),
                    'brand' => 'default',
                    'mode' => 'dark',
                    'defaults' => \array_merge($baseDefaults, $darkDefaults),
                    'overrides' => $overrides['default--dark'] ?? [],
                    'values' => \array_merge($baseDefaults, $darkDefaults, $overrides['default--dark'] ?? []),
                ],
            ],
            'css' => self::runtimeCss($overrides),
        ];
    }

    /** @param array<string, array<string, string>>|null $overrides */
    public static function runtimeCss(?array $overrides = null): string
    {
        $overrides ??= self::overrides();
        $light = $overrides['default--light'] ?? [];
        $dark = $overrides['default--dark'] ?? [];
        $parts = [];

        if ($light !== []) {
            $parts[] = self::cssBlock(':root', $light);
        }

        if ($dark !== []) {
            $parts[] = self::cssBlock(':root[data-mode="dark"]', $dark);
            $parts[] = "@media (prefers-color-scheme: dark) {\n" . self::cssBlock(':root[data-mode="auto"]', $dark, '  ') . "\n}";
        }

        return \implode("\n\n", \array_filter($parts));
    }

    /** @return array<int, array<string, mixed>> */
    private static function groups(): array
    {
        $tokens = self::baseDefaults();
        $groups = [
            ['id' => 'semantic-colors', 'label' => 'Semantic Colors', 'prefixes' => ['color-surface-', 'color-text-', 'color-border-', 'color-accent-', 'color-feedback-']],
            ['id' => 'palette', 'label' => 'Palette', 'prefixes' => ['color-neutral-', 'color-brand-', 'color-success-', 'color-warning-', 'color-danger-']],
            ['id' => 'typography', 'label' => 'Typography', 'prefixes' => ['font-', 'text-', 'fontFamily-', 'fontSize-', 'fontWeight-', 'lineHeight-']],
            ['id' => 'layout', 'label' => 'Layout', 'prefixes' => ['layout-', 'spacing-']],
            ['id' => 'radius-shadow', 'label' => 'Radius & Shadow', 'prefixes' => ['radius-', 'shadow-']],
        ];

        return \array_map(static function (array $group) use ($tokens): array {
            $items = [];
            foreach ($tokens as $key => $value) {
                foreach ($group['prefixes'] as $prefix) {
                    if (\str_starts_with($key, $prefix)) {
                        $items[] = [
                            'key' => $key,
                            'cssVar' => '--bky-' . $key,
                            'label' => self::labelForKey($key),
                            'type' => self::typeForKey($key),
                        ];
                        break;
                    }
                }
            }

            return [
                'id' => $group['id'],
                'label' => $group['label'],
                'tokens' => $items,
            ];
        }, $groups);
    }

    /** @param array<string, mixed> $raw @return array<string, array<string, string>> */
    private static function sanitizeOverrides(array $raw): array
    {
        $allowedTokens = self::allowedTokenKeys();
        $result = [];

        foreach (['default--light', 'default--dark'] as $variantId) {
            $variant = $raw[$variantId] ?? [];
            if (!\is_array($variant)) {
                continue;
            }

            foreach ($variant as $key => $value) {
                if (!\is_string($key) || !isset($allowedTokens[$key])) {
                    continue;
                }
                if (!\is_string($value) && !\is_numeric($value)) {
                    continue;
                }

                $safeValue = self::sanitizeCssValue((string) $value, self::typeForKey($key));
                if ($safeValue !== '') {
                    $result[$variantId][$key] = $safeValue;
                }
            }
        }

        return $result;
    }

    /** @return array<string, true> */
    private static function allowedTokenKeys(): array
    {
        return \array_fill_keys(\array_keys(self::baseDefaults()), true);
    }

    private static function sanitizeCssValue(string $value, string $type): string
    {
        $value = \trim($value);
        if ($value === '' || \strlen($value) > 180 || \preg_match('/[;{}<>]/', $value)) {
            return '';
        }

        if ($type === 'color') {
            return \preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value) ? $value : '';
        }

        if (!\preg_match('/^[#a-zA-Z0-9\\s.,()%+\\-\\/_\'\"]+$/', $value)) {
            return '';
        }

        return $value;
    }

    /** @param array<string, string> $tokens */
    private static function cssBlock(string $selector, array $tokens, string $indent = ''): string
    {
        $lines = [$indent . $selector . ' {'];
        foreach ($tokens as $key => $value) {
            $lines[] = $indent . '  --bky-' . $key . ': ' . $value . ';';
        }
        $lines[] = $indent . '}';

        return \implode("\n", $lines);
    }

    private static function typeForKey(string $key): string
    {
        if (\str_starts_with($key, 'color-')) {
            return 'color';
        }
        if (\str_starts_with($key, 'spacing-') || \str_starts_with($key, 'layout-') || \str_starts_with($key, 'radius-') || \str_starts_with($key, 'text-') || \str_starts_with($key, 'fontSize-')) {
            return 'dimension';
        }
        if (\str_starts_with($key, 'fontWeight-') || \str_starts_with($key, 'lineHeight-')) {
            return 'number';
        }
        if (\str_starts_with($key, 'font-') || \str_starts_with($key, 'fontFamily-')) {
            return 'font';
        }
        if (\str_starts_with($key, 'shadow-')) {
            return 'shadow';
        }
        return 'text';
    }

    private static function labelForKey(string $key): string
    {
        return \ucwords(\str_replace(['-', '_'], ' ', $key));
    }
}