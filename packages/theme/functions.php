<?php
/**
 * Blocky Theme — functions.php
 *
 * @package Blocky
 */

declare(strict_types=1);

namespace Blocky\Theme;

if (!\defined('ABSPATH')) {
    exit;
}

const BLOCKY_THEME_VERSION = '0.1.0';

// ── Setup ──────────────────────────────────────────────────────────────────────

\add_action('after_setup_theme', __NAMESPACE__ . '\setup');

function setup(): void
{
    \load_theme_textdomain('blocky', \get_template_directory() . '/languages');

    \add_theme_support('automatic-feed-links');
    \add_theme_support('title-tag');
    \add_theme_support('post-thumbnails');
    \add_theme_support('responsive-embeds');
    \add_theme_support('html5', [
        'comment-list',
        'comment-form',
        'search-form',
        'gallery',
        'caption',
        'style',
        'script',
    ]);
    \add_theme_support('editor-styles');
    \add_theme_support('block-templates');
    \add_theme_support('wp-block-styles');

    // Disable core block patterns
    \remove_theme_support('core-block-patterns');
}

// ── Enqueue assets ─────────────────────────────────────────────────────────────

\add_action('wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets');
\add_filter('script_loader_tag', __NAMESPACE__ . '\force_module_scripts', 10, 3);

function enqueue_assets(): void
{
    $template_dir = \get_template_directory_uri();
    $version      = BLOCKY_THEME_VERSION;

    // In development, prefer built assets when available and only fall back to Vite.
    if (should_use_dev_assets()) {
        $dev_url = theme_dev_asset_url();
        if ($dev_url === null) {
            return;
        }

        // Vite client (HMR)
        \wp_enqueue_script(
            'vite-client',
            $dev_url . '/@vite/client',
            [],
            null,
            ['in_footer' => false, 'strategy' => 'defer']
        );
        \wp_scripts()->registered['vite-client']->extra['type'] = 'module';

        // Theme entry
        \wp_enqueue_script(
            'blocky-theme',
            $dev_url . '/assets/js/theme.ts',
            ['vite-client'],
            null,
            ['in_footer' => true, 'strategy' => 'defer']
        );
        \wp_scripts()->registered['blocky-theme']->extra['type'] = 'module';
    } else {
        // Production: use Vite manifest
        $manifest = get_vite_manifest();

        if ($manifest) {
            $css_file = $manifest['assets/js/theme.ts']['css'][0] ?? null;
            $js_file  = $manifest['assets/js/theme.ts']['file'] ?? null;

            if ($css_file) {
                \wp_enqueue_style(
                    'blocky-theme',
                    $template_dir . '/dist/' . $css_file,
                    [],
                    $version
                );
            }
            if ($js_file) {
                \wp_enqueue_script(
                    'blocky-theme',
                    $template_dir . '/dist/' . $js_file,
                    [],
                    $version,
                    ['in_footer' => true, 'strategy' => 'defer']
                );
                \wp_scripts()->registered['blocky-theme']->extra['type'] = 'module';
            }
        }
    }
}

function force_module_scripts(string $tag, string $handle, string $src): string
{
    if (!in_array($handle, ['vite-client', 'blocky-theme'], true)) {
        return $tag;
    }

    if (str_contains($tag, 'type="module"')) {
        return $tag;
    }

    return preg_replace('/<script\s/', '<script type="module" ', $tag, 1) ?? $tag;
}

// ── Anti-flash inline script (mode detection) ─────────────────────────────────

\add_action('wp_head', __NAMESPACE__ . '\inject_mode_script', 1);

function inject_mode_script(): void
{
    ?>
    <script>
    (function(){
        var m=document.cookie.match(/bky_mode=(light|dark|auto)/);
        var b=document.cookie.match(/bky_brand=([a-z0-9_-]+)/);
        var mode=m?m[1]:'auto';
        var brand=b?b[1]:'default';
        document.documentElement.dataset.mode=mode;
        document.documentElement.dataset.brand=brand;
        if(mode==='dark'||(mode==='auto'&&window.matchMedia('(prefers-color-scheme:dark)').matches)){
            document.documentElement.classList.add('dark');
        }
    })();
    </script>
    <?php
}

// ── Editor styles ──────────────────────────────────────────────────────────────

\add_action('admin_init', __NAMESPACE__ . '\enqueue_editor_styles');

function enqueue_editor_styles(): void
{
    $template_dir = \get_template_directory_uri();

    if (should_use_dev_assets()) {
        return; // Skip in dev; editor styles loaded via Vite
    }

    $manifest = get_vite_manifest();
    if ($manifest) {
        $css_file = $manifest['assets/css/editor.css']['file'] ?? null;
        if ($css_file) {
            \add_editor_style($template_dir . '/dist/' . $css_file);
        }
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────────

/**
 * @return array<string, mixed>|null
 */
function get_vite_manifest(): ?array
{
    $manifest_path = \get_template_directory() . '/dist/.vite/manifest.json';
    if (!\file_exists($manifest_path)) {
        return null;
    }

    $content = \file_get_contents($manifest_path);
    if ($content === false) {
        return null;
    }

    /** @var array<string, mixed>|null $manifest */
    $manifest = \json_decode($content, true);
    return $manifest;
}

function should_use_dev_assets(): bool
{
    if (!(\defined('BLOCKY_DEV') && BLOCKY_DEV)) {
        return false;
    }

    return get_vite_manifest() === null && theme_dev_asset_url() !== null;
}

function theme_dev_asset_url(): ?string
{
    if (\defined('BLOCKY_THEME_ASSET_URL') && is_string(BLOCKY_THEME_ASSET_URL) && BLOCKY_THEME_ASSET_URL !== '') {
        return rtrim(BLOCKY_THEME_ASSET_URL, '/');
    }

    if (\defined('BLOCKY_ASSET_URL') && is_string(BLOCKY_ASSET_URL) && BLOCKY_ASSET_URL !== '') {
        return rtrim(BLOCKY_ASSET_URL, '/');
    }

    $env_url = getenv('BLOCKY_THEME_DEV_URL');
    if (is_string($env_url) && $env_url !== '') {
        return rtrim($env_url, '/');
    }

    $dev_host = getenv('BLOCKY_DEV_HOST') ?: '192.168.191.242';
    return 'http://' . $dev_host . ':5173';
}

// ── Body classes ───────────────────────────────────────────────────────────────

\add_filter('body_class', __NAMESPACE__ . '\body_classes');

/**
 * @param string[] $classes
 * @return string[]
 */
function body_classes(array $classes): array
{
    $classes[] = 'blocky-theme';
    if (!\is_singular()) {
        $classes[] = 'hentry';
    }
    return $classes;
}

// ── Nav menus ──────────────────────────────────────────────────────────────────

\register_nav_menus([
    'primary' => __('Primary Navigation', 'blocky'),
    'footer'  => __('Footer Navigation', 'blocky'),
]);

// ── Remove unnecessary head clutter ───────────────────────────────────────────

\remove_action('wp_head', 'wp_generator');
\remove_action('wp_head', 'wlwmanifest_link');
\remove_action('wp_head', 'rsd_link');
\remove_action('wp_head', 'wp_shortlink_wp_head');
\remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');

// ── Register theme variants ────────────────────────────────────────────────────

\add_action('blocky/register_themes', __NAMESPACE__ . '\register_theme_variants');

function register_theme_variants(\Blocky\Core\Tokens\ThemeEngine $engine): void
{
    $engine->registerVariant(new \Blocky\Core\Tokens\ThemeVariant(
        id:      'default--light',
        brand:   'default',
        mode:    'light',
        tokens:  [],
        parent:  null,
        cssFile: '',
    ));

    $engine->registerVariant(new \Blocky\Core\Tokens\ThemeVariant(
        id:      'default--dark',
        brand:   'default',
        mode:    'dark',
        tokens:  [],
        parent:  'default--light',
        cssFile: '',
    ));
}
