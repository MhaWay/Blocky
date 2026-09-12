<?php
declare(strict_types=1);

namespace Blocky\Core\Assets;

use Blocky\Core\Compiler\PageCompiler;
use Blocky\Core\Tokens\ThemeEngine;
use Blocky\Core\Tokens\ThemeSettings;

/**
 * Coordinates per-page asset enqueuing:
 * - Theme CSS (token vars + Tailwind output)
 * - Active brand variant CSS
 * - Block-specific JS islands (only when needed)
 */
final class AssetOrchestrator
{
    private Manifest $manifest;
    private string $buildUrl;

    public function __construct(
        private readonly ThemeEngine $themeEngine,
    ) {
        $this->buildUrl = rtrim(BLOCKY_CORE_URL, '/') . '/dist';
        $manifestPath = defined('BLOCKY_CORE_DIR')
            ? BLOCKY_CORE_DIR . 'dist/.vite/manifest.json'
            : '';

        $this->manifest = new Manifest($manifestPath, $this->buildUrl);
    }

    /**
     * Enqueue all assets. Hooked to `wp_enqueue_scripts`.
     */
    public function enqueue(): void
    {
        $isDev = $this->shouldUseDevAssets();

        if ($isDev) {
            $this->enqueueDev();
        } else {
            $this->enqueueProd();
        }

        $this->enqueueCompiledPageCss();
        $this->enqueueThemeVariant();
        \add_action('wp_print_styles', function (): void {
            $this->enqueueThemeOverrides();
        }, 99);
        $this->localizeConfig();
    }

    // ── Dev mode (Vite HMR) ───────────────────────────────────────────────────

    private function enqueueDev(): void
    {
        $devUrl = $this->coreDevAssetUrl();
        if ($devUrl === null) {
            return;
        }

        // Vite HMR client
        \wp_enqueue_script_module(
            'blocky-vite-client',
            "{$devUrl}/@vite/client",
            [],
            null,
        );

        // Main plugin entry
        \wp_enqueue_script_module(
            'blocky-core',
            "{$devUrl}/src/index.ts",
            ['blocky-vite-client'],
            null,
        );
    }

    // ── Production mode (manifest-driven) ─────────────────────────────────────

    private function enqueueProd(): void
    {
        if (!$this->manifest->isAvailable()) {
            return;
        }

        $entry = 'src/index.ts';
        $url   = $this->manifest->url($entry);

        if ($url === null) {
            return;
        }

        // CSS chunks
        foreach ($this->manifest->cssFor($entry) as $i => $cssUrl) {
            \wp_enqueue_style("blocky-core-css-{$i}", $cssUrl, [], null);
        }

        // JS module
        \wp_enqueue_script_module('blocky-core', $url, [], null);
    }

    // ── Brand/mode variant CSS ─────────────────────────────────────────────────

    private function enqueueThemeVariant(): void
    {
        $variant = $this->themeEngine->getActiveVariant();
        if ($variant === null || $variant->cssFile === '') {
            return;
        }

        \wp_enqueue_style(
            'blocky-theme-variant',
            $variant->cssFile,
            ['blocky-core-css-0'],
            null
        );
    }

    private function enqueueThemeOverrides(): void
    {
        $css = ThemeSettings::runtimeCss();
        if ($css === '') {
            return;
        }

        \wp_register_style('blocky-theme-overrides', false, [], BLOCKY_CORE_VERSION);
        \wp_enqueue_style('blocky-theme-overrides');
        \wp_add_inline_style('blocky-theme-overrides', $css);
    }

    private function enqueueCompiledPageCss(): void
    {
        if (!\is_singular()) {
            return;
        }

        $postId = \get_queried_object_id();
        if (!\is_int($postId) || $postId <= 0) {
            return;
        }

        $css = \get_post_meta($postId, PageCompiler::CSS_CACHE_META_KEY, true);
        if (!\is_string($css) || $css === '') {
            return;
        }

        \wp_register_style('blocky-page-css', false, [], BLOCKY_CORE_VERSION);
        \wp_enqueue_style('blocky-page-css');
        \wp_add_inline_style('blocky-page-css', $css);
    }

    // ── JS config ─────────────────────────────────────────────────────────────

    private function localizeConfig(): void
    {
        if (!\wp_script_is('blocky-core', 'enqueued')) {
            return;
        }

        $config = [
            'restUrl'       => \esc_url_raw(\rest_url('blocky/v1/')),
            'nonce'         => \wp_create_nonce('wp_rest'),
            'activeBrand'   => $this->themeEngine->getActiveBrand(),
            'activeMode'    => $this->themeEngine->getActiveMode(),
            'variants'      => $this->themeEngine->getVariantsForJs(),
        ];

        \wp_add_inline_script(
            'blocky-core',
            'window.BlockyConfig = ' . \wp_json_encode($config) . ';',
            'before'
        );
    }

    private function shouldUseDevAssets(): bool
    {
        if (!(defined('BLOCKY_DEV') && BLOCKY_DEV)) {
            return false;
        }

        return $this->coreDevAssetUrl() !== null;
    }

    private function coreDevAssetUrl(): ?string
    {
        if (defined('BLOCKY_CORE_ASSET_URL') && is_string(BLOCKY_CORE_ASSET_URL) && BLOCKY_CORE_ASSET_URL !== '') {
            return rtrim(BLOCKY_CORE_ASSET_URL, '/');
        }

        $envUrl = getenv('BLOCKY_CORE_DEV_URL');
        if (is_string($envUrl) && $envUrl !== '') {
            return rtrim($envUrl, '/');
        }

        return null;
    }
}
