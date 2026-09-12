<?php
/**
 * @package Blocky\Core\Support
 */

declare(strict_types=1);

namespace Blocky\Core\Support;

use Blocky\Core\Blocks\Node;
use Blocky\Core\Blocks\Renderer\Pipeline;
use Blocky\Core\Tokens\ThemeEngine;

/**
 * Immutable context passed through the render pipeline.
 */
final class RenderContext
{
    /** @var array<string, Node> */
    private readonly array $nodeMap;

    private function __construct(
        array                         $nodeMap,
        private readonly ?ThemeEngine $themeEngine,
        private readonly ?Pipeline    $pipeline,
        private readonly bool         $editorMode = false,
        private readonly int          $currentPostId = 0,
    ) {
        $this->nodeMap = $nodeMap;
    }

    /**
     * @param array<string, Node> $nodeMap
     */
    public static function make(
        array $nodeMap,
        ThemeEngine $themeEngine,
        Pipeline $pipeline,
        bool $editorMode = false,
        int $currentPostId = 0,
    ): self {
        return new self($nodeMap, $themeEngine, $pipeline, $editorMode, $currentPostId);
    }

    /**
     * Minimal context for Gutenberg-compatibility renders.
     */
    public static function makeMinimal(): self
    {
        return new self([], null, null);
    }

    /**
     * Add builder-only metadata for canvas selection and hover controls.
     *
     * @param array<string, string|bool|null> $attrs
     * @return array<string, string|bool|null>
     */
    public function blockAttrs(Node $node, array $attrs = []): array
    {
        $attrs['class'] = self::appendClass(
            (string) ($attrs['class'] ?? ''),
            self::nodeUtilityClasses($node),
        );
        $utilityStyles = self::nodeUtilityStyles($node);
        if ($utilityStyles !== '') {
            $attrs['style'] = self::appendStyle((string) ($attrs['style'] ?? ''), $utilityStyles);
        }

        if (!$this->editorMode) {
            $interactionPayload = self::interactionPayload($node);
            if ($interactionPayload !== null) {
                $attrs['data-bky-interactions'] = $interactionPayload;
            }
            return $attrs;
        }

        $attrs['data-bky-id']   = $node->id;
        $attrs['data-bky-type'] = $node->type;

        return $attrs;
    }

    /**
     * Add builder-only metadata and visual hooks for container blocks.
     *
     * @param array<string, string|bool|null> $attrs
     * @return array<string, string|bool|null>
     */
    public function containerAttrs(Node $node, array $attrs = []): array
    {
        if ($this->editorMode) {
            $attrs['data-bky-container'] = '1';
            $attrs['class'] = self::appendClass((string) ($attrs['class'] ?? ''), 'bky-edit-container');
        }

        return $this->blockAttrs($node, $attrs);
    }

    /**
     * Add builder-only metadata for drop targets inside container slots.
     *
     * @param array<string, string|bool|null> $attrs
     * @return array<string, string|bool|null>
     */
    public function slotAttrs(Node $node, string $slotName = 'default', array $attrs = []): array
    {
        if (!$this->editorMode) {
            return $attrs;
        }

        $attrs['data-bky-slot-owner'] = $node->id;
        $attrs['data-bky-slot-name'] = $slotName;
        $attrs['class'] = self::appendClass((string) ($attrs['class'] ?? ''), 'bky-edit-slot');

        if (empty($node->slots[$slotName] ?? [])) {
            $attrs['class'] = self::appendClass((string) $attrs['class'], 'bky-edit-empty-slot');
        }

        return $attrs;
    }

    public function isEditorMode(): bool
    {
        return $this->editorMode;
    }

    public function currentPostId(): int
    {
        return $this->currentPostId;
    }

    /**
     * Get a node from the document map.
     */
    public function getNode(string $id): ?Node
    {
        return $this->nodeMap[$id] ?? null;
    }

    /**
     * Render a single node from the current document map.
     */
    public function renderNode(Node $node): HtmlString
    {
        if ($this->pipeline === null) {
            return HtmlString::of('');
        }

        return $this->pipeline->renderNode($node, $this);
    }

    public function renderDocument(string $json, ?bool $editorMode = null): HtmlString
    {
        if ($this->pipeline === null) {
            return HtmlString::of('');
        }

        return $this->pipeline->renderDocument($json, $editorMode ?? $this->editorMode, $this->currentPostId);
    }

    /**
     * Render all nodes in a named slot of the given parent node.
     */
    public function renderSlot(Node $parent, string $slotName = 'default'): HtmlString
    {
        if ($this->pipeline === null) {
            return HtmlString::of('');
        }
        $nodeIds = $parent->slots[$slotName] ?? [];
        return $this->pipeline->renderSlot($nodeIds, $this);
    }

    /**
     * Get the current active theme variant (brand + mode).
     */
    public function getCurrentTheme(): string
    {
        return $this->themeEngine?->getActiveVariantId() ?? 'default--light';
    }

    /**
     * Resolve variant CSS classes for a block node.
     *
     * @param array<string, array<string, string>> $variantMap
     * @return string
     */
    public function resolveVariantClasses(Node $node, array $variantMap): string
    {
        $classes = [];
        foreach ($variantMap as $key => $options) {
            // Prefer explicit variant selection, then props value, then schema default
            $selected = $node->variants[$key]
                ?? $node->props[$key]
                ?? array_key_first($options);
            if ($selected !== null && isset($options[$selected])) {
                $classes[] = $options[$selected];
            }
        }
        return implode(' ', array_filter($classes));
    }

    private static function appendClass(string $existing, string $className): string
    {
        return trim($existing . ' ' . $className);
    }

    private static function appendStyle(string $existing, string $style): string
    {
        $existing = trim($existing);
        $style = trim($style);
        if ($existing === '') {
            return $style;
        }
        if ($style === '') {
            return $existing;
        }
        return rtrim($existing, ';') . '; ' . $style;
    }

    private static function nodeUtilityClasses(Node $node): string
    {
        $rawClasses = $node->props['twClasses'] ?? [];
        if (is_string($rawClasses)) {
            $rawClasses = preg_split('/\s+/', trim($rawClasses)) ?: [];
        }
        if (!is_array($rawClasses)) {
            return '';
        }

        $classes = [];
        foreach ($rawClasses as $className) {
            if (!is_string($className)) {
                continue;
            }
            $safeClassName = self::sanitizeUtilityClass($className);
            if ($safeClassName !== '') {
                $classes[] = $safeClassName;
            }
        }

        return implode(' ', array_values(array_unique($classes)));
    }

    private static function sanitizeUtilityClass(string $className): string
    {
        return preg_replace('/[^A-Za-z0-9_:\-\[\]\/\.%#(),!]/', '', trim($className)) ?? '';
    }

    private static function nodeUtilityStyles(Node $node): string
    {
        $rawVars = $node->props['twColorVars'] ?? [];
        $styles = [];

        if (is_array($rawVars)) {
            foreach ($rawVars as $name => $value) {
                if (!is_string($name) || !is_string($value)) {
                    continue;
                }
                $safeName = self::sanitizeUtilityVariableName($name);
                $safeValue = self::sanitizeHexColor($value);
                if ($safeName !== '' && $safeValue !== '') {
                    $styles[] = $safeName . ': ' . $safeValue;
                }
            }
        }

        $rawStyleVars = $node->props['twStyleVars'] ?? [];
        if (is_array($rawStyleVars)) {
            foreach ($rawStyleVars as $name => $value) {
                if (!is_string($name) || !is_string($value)) {
                    continue;
                }
                $safeName = self::sanitizeUtilityStyleVariableName($name);
                $safeValue = self::sanitizeUtilityStyleVariableValue($safeName, $value);
                if ($safeName !== '' && $safeValue !== '') {
                    if (preg_match('/^--bky-tw-[a-z0-9-]+-background-image-url$/', $safeName)) {
                        $prefix = substr($safeName, 0, -strlen('-background-image-url'));
                        $styles[] = 'background-image: url(' . $safeValue . ')';
                        $styles[] = 'background-position: var(' . $prefix . '-background-position-x, 50%) var(' . $prefix . '-background-position-y, 50%)';
                        $styles[] = 'background-size: cover';
                        $styles[] = 'background-repeat: no-repeat';
                        continue;
                    }
                    $styles[] = $safeName . ': ' . $safeValue;
                }
            }
        }

        $gridColumnStart = self::sanitizeGridLine($node->props['gridColumnStart'] ?? null);
        if ($gridColumnStart !== '') {
            $styles[] = 'grid-column-start: ' . $gridColumnStart;
        }

        $gridRowStart = self::sanitizeGridLine($node->props['gridRowStart'] ?? null);
        if ($gridRowStart !== '') {
            $styles[] = 'grid-row-start: ' . $gridRowStart;
        }

        return implode('; ', $styles);
    }

    private static function sanitizeUtilityVariableName(string $name): string
    {
        $name = trim($name);
        if (!preg_match('/^--bky-tw-[a-z0-9-]+$/', $name)) {
            return '';
        }
        return $name;
    }

    private static function sanitizeUtilityStyleVariableName(string $name): string
    {
        $name = trim($name);
        if (preg_match('/^--bky-space-(?:[a-z0-9]+-)?(?:margin|padding)-(?:top|right|bottom|left)$/', $name)) {
            return $name;
        }
        if (preg_match('/^--bky-tw-[a-z0-9-]+-background-gradient$/', $name)) {
            return $name;
        }
        if (preg_match('/^--bky-tw-[a-z0-9-]+-background-image-url$/', $name)) {
            return $name;
        }
        if (preg_match('/^--bky-tw-[a-z0-9-]+-background-position-[xy]$/', $name)) {
            return $name;
        }
        return '';
    }

    private static function sanitizeUtilityStyleVariableValue(string $name, string $value): string
    {
        if (preg_match('/^--bky-space-(?:[a-z0-9]+-)?(?:margin|padding)-(?:top|right|bottom|left)$/', $name)) {
            return self::sanitizeCssLength($value);
        }
        if (preg_match('/^--bky-tw-[a-z0-9-]+-background-gradient$/', $name)) {
            return self::sanitizeLinearGradientArgs($value);
        }
        if (preg_match('/^--bky-tw-[a-z0-9-]+-background-image-url$/', $name)) {
            return self::sanitizeBackgroundImageUrl($value);
        }
        if (preg_match('/^--bky-tw-[a-z0-9-]+-background-position-[xy]$/', $name)) {
            return self::sanitizeBackgroundPosition($value);
        }
        return '';
    }

    private static function sanitizeBackgroundImageUrl(string $value): string
    {
        $value = trim($value);
        if (!preg_match('#^(https?://|/)[^\s"\'()<>]+$#i', $value)) {
            return '';
        }

        return '"' . $value . '"';
    }

    private static function sanitizeBackgroundPosition(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^(?:\d|[1-9]\d|100)%$/', $value)) {
            return '';
        }

        return $value;
    }

    private static function interactionPayload(Node $node): ?string
    {
        $rawInteractions = $node->props['interactions'] ?? null;
        if (!is_array($rawInteractions)) {
            return null;
        }

        $allowedEvents = ['click', 'hover', 'focus', 'load'];
        $allowedActions = ['overlay.open', 'overlay.close', 'overlay.toggle', 'class.add', 'class.remove', 'class.toggle', 'custom.emit'];
        $allowedDevices = ['any', 'desktop', 'tablet', 'mobile'];
        $allowedLoginStates = ['any', 'logged-in', 'logged-out'];
        $payload = [];

        foreach ($rawInteractions as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $event = is_string($entry['event'] ?? null) ? trim($entry['event']) : '';
            $action = is_string($entry['action'] ?? null) ? trim($entry['action']) : '';
            if (!in_array($event, $allowedEvents, true) || !in_array($action, $allowedActions, true)) {
                continue;
            }

            $target = is_string($entry['target'] ?? null) ? trim($entry['target']) : '';
            $className = is_string($entry['className'] ?? null) ? trim($entry['className']) : '';
            $delay = is_numeric($entry['delay'] ?? null) ? max(0, (int) $entry['delay']) : 0;
            $debounce = is_numeric($entry['debounce'] ?? null) ? max(0, (int) $entry['debounce']) : 0;
            $throttle = is_numeric($entry['throttle'] ?? null) ? max(0, (int) $entry['throttle']) : 0;
            $device = is_string($entry['device'] ?? null) ? trim($entry['device']) : 'any';
            $loginState = is_string($entry['loginState'] ?? null) ? trim($entry['loginState']) : 'any';
            if (!in_array($device, $allowedDevices, true)) {
                $device = 'any';
            }
            if (!in_array($loginState, $allowedLoginStates, true)) {
                $loginState = 'any';
            }

            $payload[] = [
                'event' => $event,
                'action' => $action,
                'target' => $target,
                'className' => $className,
                'delay' => $delay,
                'debounce' => $debounce,
                'throttle' => $throttle,
                'once' => ($entry['once'] ?? false) === true,
                'preventDefault' => ($entry['preventDefault'] ?? true) !== false,
                'stopPropagation' => ($entry['stopPropagation'] ?? false) === true,
                'device' => $device,
                'loginState' => $loginState,
                'queryKey' => is_string($entry['queryKey'] ?? null) ? trim($entry['queryKey']) : '',
                'queryValue' => is_string($entry['queryValue'] ?? null) ? trim($entry['queryValue']) : '',
                'cookieKey' => is_string($entry['cookieKey'] ?? null) ? trim($entry['cookieKey']) : '',
                'cookieValue' => is_string($entry['cookieValue'] ?? null) ? trim($entry['cookieValue']) : '',
            ];
        }

        if ($payload === []) {
            return null;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            return null;
        }

        return base64_encode($encoded);
    }

    private static function sanitizeHexColor(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
            return '';
        }
        return $value;
    }

    private static function sanitizeCssLength(string $value): string
    {
        $value = str_replace(',', '.', trim($value));
        if (!preg_match('/^-?(?:\d+|\d*\.\d+)(?:px|rem|em|%|vh|vw)$/', $value)) {
            return '';
        }
        return $value;
    }

    private static function sanitizeLinearGradientArgs(string $value): string
    {
        $value = preg_replace('/\s+/', '', trim($value)) ?? '';
        if (!preg_match('/^(\d{1,3})deg,([^,]+),([^,]+)$/', $value, $matches)) {
            return '';
        }

        $angle = filter_var($matches[1] ?? '', FILTER_VALIDATE_INT);
        if (!is_int($angle) || $angle < 0 || $angle > 360) {
            return '';
        }

        $start = self::sanitizeHexColor($matches[2] ?? '');
        $end = self::sanitizeHexColor($matches[3] ?? '');
        if ($start === '' || $end === '') {
            return '';
        }

        return $angle . 'deg,' . $start . ',' . $end;
    }

    private static function sanitizeGridLine(mixed $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return '';
        }

        $number = filter_var((string) $value, FILTER_VALIDATE_INT);
        if (!is_int($number) || $number < 1 || $number > 12) {
            return '';
        }

        return (string) $number;
    }
}
