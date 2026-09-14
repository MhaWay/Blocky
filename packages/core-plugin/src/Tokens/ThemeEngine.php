<?php
declare(strict_types=1);

namespace Blocky\Core\Tokens;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

/**
 * Singleton theme engine — manages variants and detects active brand/mode.
 */
final class ThemeEngine
{
    private static ?self $instance = null;

    /** @var array<string, ThemeVariant> */
    private array $variants = [];

    private ?string $activeBrand = null;
    private ?string $activeMode  = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a theme variant.
     */
    public function registerVariant(ThemeVariant $variant): void
    {
        $this->variants[$variant->id] = $variant;
    }

    /**
     * Get a variant by ID.
     */
    public function getVariant(string $id): ?ThemeVariant
    {
        return $this->variants[$id] ?? null;
    }

    /**
     * Get all variants.
     *
     * @return array<string, ThemeVariant>
     */
    public function allVariants(): array
    {
        return $this->variants;
    }

    /**
     * Get variants for a specific brand.
     *
     * @return ThemeVariant[]
     */
    public function variantsForBrand(string $brand): array
    {
        return array_values(array_filter(
            $this->variants,
            fn(ThemeVariant $v) => $v->brand === $brand
        ));
    }

    /**
     * Detect the active brand from cookie or default.
     */
    public function getActiveBrand(): string
    {
        if ($this->activeBrand !== null) {
            return $this->activeBrand;
        }

        $cookie = $_COOKIE['bky_brand'] ?? '';
        $brand  = preg_match('/^[a-z0-9\-]{1,64}$/', $cookie) ? $cookie : 'default';

        // Ensure the requested brand is registered
        $hasBrand = !empty(array_filter(
            $this->variants,
            fn(ThemeVariant $v) => $v->brand === $brand
        ));

        $this->activeBrand = $hasBrand ? $brand : 'default';
        return $this->activeBrand;
    }

    /**
     * Detect the active mode from cookie or OS preference (server-side default: light).
     */
    public function getActiveMode(): string
    {
        if ($this->activeMode !== null) {
            return $this->activeMode;
        }

        $cookie = $_COOKIE['bky_mode'] ?? '';
        $this->activeMode = in_array($cookie, ['light', 'dark'], true) ? $cookie : 'light';
        return $this->activeMode;
    }

    /**
     * Get active variant ID ({brand}--{mode}).
     */
    public function getActiveVariantId(): string
    {
        return $this->getActiveBrand() . '--' . $this->getActiveMode();
    }

    /**
     * Get the active ThemeVariant object.
     */
    public function getActiveVariant(): ?ThemeVariant
    {
        return $this->getVariant($this->getActiveVariantId())
            ?? $this->getVariant($this->getActiveBrand() . '--light')
            ?? $this->getVariant('default--light');
    }

    /**
     * Get serializable variant metadata for REST / JS.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getVariantsForJs(): array
    {
        $result = [];
        foreach ($this->variants as $id => $v) {
            $result[$id] = $v->toArray();
        }
        return $result;
    }
}
