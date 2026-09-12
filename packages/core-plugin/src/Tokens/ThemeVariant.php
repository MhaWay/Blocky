<?php
declare(strict_types=1);

namespace Blocky\Core\Tokens;

/**
 * A theme variant: a named combination of brand + mode with token overrides.
 */
final class ThemeVariant
{
    /**
     * @param string               $id       Unique variant ID, format: "{brand}--{mode}"
     * @param string               $brand    Brand slug (e.g. "default", "client-a")
     * @param string               $mode     Mode slug ("light" | "dark")
     * @param array<string, mixed> $tokens   Token overrides for this variant
     * @param string|null          $parent   Parent variant ID (cascade source)
     * @param string               $cssFile  URL to CSS file for this variant (if any)
     */
    public function __construct(
        public readonly string  $id,
        public readonly string  $brand,
        public readonly string  $mode,
        public readonly array   $tokens  = [],
        public readonly ?string $parent  = null,
        public readonly string  $cssFile = '',
    ) {}

    public function isLight(): bool
    {
        return $this->mode === 'light';
    }

    public function isDark(): bool
    {
        return $this->mode === 'dark';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'      => $this->id,
            'brand'   => $this->brand,
            'mode'    => $this->mode,
            'cssFile' => $this->cssFile,
        ];
    }
}
