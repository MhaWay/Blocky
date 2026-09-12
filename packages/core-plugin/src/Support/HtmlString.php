<?php
/**
 * @package Blocky\Core\Support
 */

declare(strict_types=1);

namespace Blocky\Core\Support;

/**
 * Trusted HTML string wrapper — prevents accidental double-escaping.
 * Use HtmlString::of() only with already-escaped or trusted output.
 */
final class HtmlString
{
    private function __construct(
        private readonly string $html
    ) {}

    /**
     * Create from a trusted HTML string.
     * WARNING: Do NOT pass unsanitised user input here.
     */
    public static function of(string $html): self
    {
        return new self($html);
    }

    /**
     * Build element: `<tag attrs>children</tag>`
     *
     * @param array<string, string|bool|null> $attrs
     */
    public static function element(
        string $tag,
        array $attrs = [],
        string $children = '',
    ): self {
        $attrStr = self::buildAttrs($attrs);
        $safeTag = preg_replace('/[^a-z0-9-]/', '', strtolower($tag)) ?? 'div';

        return new self("<{$safeTag}{$attrStr}>{$children}</{$safeTag}>");
    }

    /**
     * Build a self-closing element.
     *
     * @param array<string, string|bool|null> $attrs
     */
    public static function void(string $tag, array $attrs = []): self
    {
        $attrStr = self::buildAttrs($attrs);
        $safeTag = preg_replace('/[^a-z0-9-]/', '', strtolower($tag)) ?? 'img';
        return new self("<{$safeTag}{$attrStr}>");
    }

    public function toString(): string
    {
        return $this->html;
    }

    public function __toString(): string
    {
        return $this->html;
    }

    /**
     * Build an escaped HTML attribute string.
     *
     * @param array<string, string|bool|null> $attrs
     */
    public static function attrs(array $attrs): string
    {
        return self::buildAttrs($attrs);
    }

    /**
     * @param array<string, string|bool|null> $attrs
     */
    private static function buildAttrs(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            $safeName = preg_replace('/[^a-z0-9_:\-]/', '', strtolower($name)) ?? '';
            if ($value === true) {
                $parts[] = $safeName;
            } else {
                $parts[] = $safeName . '="' . \esc_attr($value) . '"';
            }
        }
        return $parts ? ' ' . implode(' ', $parts) : '';
    }
}
