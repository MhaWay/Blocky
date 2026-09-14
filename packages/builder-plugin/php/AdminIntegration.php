<?php
declare(strict_types=1);

namespace Blocky\Builder;

/**
 * WordPress admin integrations for Blocky Builder:
 *  – "Blocky Editor" row action in the post/page list.
 *  – "Blocky Editor" button in the WP admin bar while on a post edit screen.
 *  – Meta box shortcut on the post edit sidebar.
 *  – Block-editor overlay when a post already has a Blocky document.
 */
final class AdminIntegration
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct() {}

    public function boot(): void
    {
        \add_filter('page_row_actions',          [$this, 'addRowAction'],          10, 2);
        \add_filter('post_row_actions',          [$this, 'addRowAction'],          10, 2);
        \add_action('admin_bar_menu',            [$this, 'addAdminBarNode'],       200);
        \add_filter('manage_pages_columns',        [$this, 'addBuiltColumn'],         10);
        \add_filter('manage_posts_columns',        [$this, 'addBuiltColumn'],         10);
        \add_action('manage_pages_custom_column',  [$this, 'renderBuiltColumn'],      10, 2);
        \add_action('manage_posts_custom_column',  [$this, 'renderBuiltColumn'],      10, 2);
        \add_action('add_meta_boxes',            [$this, 'registerMetaBox']);
        \add_action('admin_enqueue_scripts',     [$this, 'enqueueAdminStyles']);
        \add_action('enqueue_block_editor_assets', [$this, 'blockEditorIntegration']);
    }

    // ── Row actions ──────────────────────────────────────────────────────────

    /**
     * @param  array<string, string> $actions
     * @return array<string, string>
     */
    public function addRowAction(array $actions, \WP_Post $post): array
    {
        if (!\current_user_can('edit_post', $post->ID)) {
            return $actions;
        }

        $url   = \esc_url(\admin_url('admin.php?page=blocky-builder&post_id=' . $post->ID));
        $built = $this->isBlockyBuilt((int) $post->ID);
        $label = $built
            ? \__('Edit with Blockwork', 'blocky')
            : \__('Blockwork Editor', 'blocky');
        $link  = '<a href="' . $url . '">' . \esc_html($label) . '</a>';

        // Elementor-style assignment: on Blocky pages the builder link leads.
        if ($built) {
            return ['blocky_editor' => $link] + $actions;
        }

        // Insert after "edit" action when present; otherwise prepend.
        $merged   = [];
        $inserted = false;
        foreach ($actions as $key => $html) {
            $merged[$key] = $html;
            if ($key === 'edit' && !$inserted) {
                $merged['blocky_editor'] = $link;
                $inserted = true;
            }
        }
        if (!$inserted) {
            $merged = ['blocky_editor' => $link] + $merged;
        }

        return $merged;
    }

    // ── 'Built with Blocky' admin column ──────────────────────────────────

    /**
     * @param  array<string, string> $columns
     * @return array<string, string>
     */
    public function addBuiltColumn(array $columns): array
    {
        $with = [];
        foreach ($columns as $key => $label) {
            $with[$key] = $label;
            if ($key === 'title') {
                $with['blocky_built'] = __('Blockwork', 'blocky');
            }
        }
        if (!isset($with['blocky_built'])) {
            $with = ['blocky_built' => __('Blockwork', 'blocky')] + $with;
        }
        return $with;
    }

    public function renderBuiltColumn(string $column, int $postId): void
    {
        if ($column !== 'blocky_built' || !$this->isBlockyBuilt($postId)) {
            return;
        }

        echo '<span class="blocky-built-badge" title="' . esc_attr__('Built with Blockwork', 'blocky') . '">' . esc_html__('Blockwork', 'blocky') . '</span>';
    }

    /**
     * Application rule: a page belongs to Blocky when Blocky created it or
     * saved its document (marker), or when a document exists even without
     * the marker (pages built before the rule shipped).
     */
    public function isBlockyBuilt(int $postId): bool
    {
        if (\get_post_meta($postId, \Blocky\Core\Rest\DocumentController::EDITOR_META_KEY, true) === 'blocky') {
            return true;
        }

        return \get_post_meta($postId, '_blocky_document', true) !== '';
    }

    // ── Admin bar button ─────────────────────────────────────────────────────

    public function addAdminBarNode(\WP_Admin_Bar $bar): void
    {
        if (!\is_admin()) {
            return;
        }

        $screen = \get_current_screen();
        if (!$screen || $screen->base !== 'post') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $postId = \absint($_GET['post'] ?? 0);
        if (!$postId || !\current_user_can('edit_post', $postId)) {
            return;
        }

        $bar->add_node([
            'id'    => 'blocky-editor-link',
            'title' => '<span class="ab-icon dashicons dashicons-grid-view" aria-hidden="true"></span>'
                     . \esc_html__('Blockwork Editor', 'blocky'),
            'href'  => \esc_url(\admin_url('admin.php?page=blocky-builder&post_id=' . $postId)),
            'meta'  => [
                'class' => 'blocky-adminbar-btn',
                'title' => \__('Edit with Blockwork Builder', 'blocky'),
            ],
        ]);
    }

    // ── Sidebar meta box ─────────────────────────────────────────────────────

    public function registerMetaBox(): void
    {
        \add_meta_box(
            'blocky-editor-metabox',
            \__('Blockwork Editor', 'blocky'),
            [$this, 'renderMetaBox'],
            null,   // all post-type edit screens
            'side',
            'high',
        );
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $url         = \esc_url(\admin_url('admin.php?page=blocky-builder&post_id=' . $post->ID));
        $hasDocument = \get_post_meta($post->ID, '_blocky_document', true) !== '';
        $label       = $hasDocument
            ? \__('Continue in Blockwork Editor', 'blocky')
            : \__('Open in Blockwork Editor', 'blocky');

        if ($hasDocument) {
            echo '<p class="blocky-metabox-notice">'
               . \esc_html__('This page is edited with Blockwork Builder.', 'blocky')
               . '</p>';
        }

        echo '<a href="' . $url . '" class="button blocky-metabox-btn">'
           . \esc_html($label)
           . '</a>';
    }

    // ── Block-editor overlay ─────────────────────────────────────────────────

    /**
     * Fired by enqueue_block_editor_assets.
     * If the post already has a Blocky document, injects an overlay that
     * covers the editor writing area, like Elementor's "Edit with Elementor" notice.
     */
    public function blockEditorIntegration(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $postId = \absint($_GET['post'] ?? 0);
        if (!$postId) {
            return;
        }

        $hasDocument = \get_post_meta($postId, '_blocky_document', true) !== '';
        $builderUrl  = \admin_url('admin.php?page=blocky-builder&post_id=' . $postId);

        // Pass data to JS via wp_localize_script (wp-blocks is always loaded in the block editor).
        \wp_localize_script('wp-blocks', 'blockyEditorData', [
            'hasDocument' => $hasDocument,
            'builderUrl'  => \esc_url_raw($builderUrl),
            'i18n'        => [
                'title'  => \__('Blockwork Editor', 'blocky'),
                'notice' => \__('This page is edited with Blockwork Builder.', 'blocky'),
                'cta'    => \__('Continue in Blockwork Editor', 'blocky'),
            ],
        ]);

        if ($hasDocument) {
            \wp_add_inline_script('wp-edit-post', $this->getOverlayScript(), 'after');
            \wp_add_inline_style('wp-edit-post', $this->getBlockEditorStyles());
        }

        $this->enqueueConvertButton($postId, $hasDocument, $builderUrl);
    }

    // ── Gutenberg header "Edit with Blocky" button ──────────────────────────

    private function enqueueConvertButton(int $postId, bool $hasDocument, string $builderUrl): void
    {
        $config = \wp_json_encode([
            'restUrl'       => \rest_url('blocky/v1/'),
            'nonce'         => \wp_create_nonce('wp_rest'),
            'builderUrl'    => \esc_url_raw($builderUrl),
            'postId'        => $postId,
            'hasDocument'   => $hasDocument,
            'label'         => \__('Edit with Blockwork', 'blocky'),
            'savingLabel'   => \__('Saving…', 'blocky'),
        ]);

        echo '<script>window.blockyConvertConfig = ' . $config . ';</script>'; // phpcs:ignore WordPress.Security.EscapeOutput

        $buildDir = BLOCKY_BUILDER_DIR . 'dist/';
        $manifestPath = $buildDir . '.vite/manifest.json';

        if (defined('BLOCKY_DEV') && BLOCKY_DEV && !file_exists($manifestPath)) {
            \wp_enqueue_script_module(
                'blocky-gutenberg-button',
                $this->devAssetUrl() . '/src/gutenberg.ts',
                [],
                null
            );
            return;
        }

        if (!file_exists($manifestPath)) {
            return;
        }

        $manifest = \json_decode((string) \file_get_contents($manifestPath), true);
        $entry = is_array($manifest) ? ($manifest['src/gutenberg.ts'] ?? null) : null;
        if (!is_array($entry) || !isset($entry['file']) || !is_string($entry['file'])) {
            return;
        }

        $assetUrl = \rtrim(BLOCKY_BUILDER_URL, '/') . '/dist';
        foreach ((array) ($entry['css'] ?? []) as $i => $cssFile) {
            if (is_string($cssFile)) {
                \wp_enqueue_style('blocky-gutenberg-css-' . $i, $assetUrl . '/' . $cssFile, [], null);
            }
        }
        \wp_enqueue_script_module('blocky-gutenberg-button', $assetUrl . '/' . $entry['file'], [], null);
    }

    private function devAssetUrl(): string
    {
        if (defined('BLOCKY_BUILDER_ASSET_URL')) {
            return \rtrim(BLOCKY_BUILDER_ASSET_URL, '/');
        }
        $envUrl = \getenv('BLOCKY_BUILDER_DEV_URL');
        if (is_string($envUrl) && $envUrl !== '') {
            return \rtrim($envUrl, '/');
        }
        return 'http://' . (getenv('BLOCKY_DEV_HOST') ?: '192.168.191.242') . ':5174';
    }

    private function getOverlayScript(): string
    {
        // Vanilla-JS overlay injected after Gutenberg mounts.
        // Uses wp.domReady and polls for the content skeleton region.
        return <<<'JS'
( function () {
    var data = window.blockyEditorData || {};
    if ( ! data.hasDocument ) { return; }

    wp.domReady( function () {
        var MAX_TRIES = 40;
        var tries     = 0;

        var timer = setInterval( function () {
            tries++;
            if ( tries > MAX_TRIES ) {
                clearInterval( timer );
                return;
            }

            // Target the editor content column (compatible across WP 6.x versions).
            var contentRegion =
                document.querySelector( '.interface-interface-skeleton__content' ) ||
                document.querySelector( '.edit-post-layout__content' ) ||
                document.querySelector( '.editor-editor-interface__body' );

            if ( ! contentRegion ) { return; }
            if ( document.getElementById( 'blocky-editor-overlay' ) ) {
                clearInterval( timer );
                return;
            }

            clearInterval( timer );

            var i18n    = data.i18n    || {};
            var overlay = document.createElement( 'div' );
            overlay.id  = 'blocky-editor-overlay';
            overlay.setAttribute( 'role', 'region' );
            overlay.setAttribute( 'aria-label', i18n.title || 'Blockwork Editor' );

            overlay.innerHTML =
                '<div class="blocky-ov-inner">' +
                    '<span class="blocky-ov-logo" aria-hidden="true">&#9783;</span>' +
                    '<h2 class="blocky-ov-title">' + ( i18n.title  || 'Blockwork Editor' ) + '</h2>' +
                    '<p  class="blocky-ov-desc">'  + ( i18n.notice || 'This page is edited with Blockwork Builder.' ) + '</p>' +
                    '<a  class="blocky-ov-btn" href="' + data.builderUrl + '">' +
                        ( i18n.cta || 'Continue in Blockwork Editor' ) +
                    '</a>' +
                '</div>';

            contentRegion.style.position = 'relative';
            contentRegion.appendChild( overlay );
        }, 150 );
    } );
}() );
JS;
    }

    private function getBlockEditorStyles(): string
    {
        return '
#blocky-editor-overlay {
    position: absolute;
    inset: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(240, 240, 241, 0.97);
    backdrop-filter: blur(3px);
}
.blocky-ov-inner {
    text-align: center;
    padding: 52px 44px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 6px 40px rgba(0,0,0,.14);
    max-width: 480px;
    width: 90%;
}
.blocky-ov-logo {
    display: block;
    font-size: 54px;
    color: #7c3aed;
    margin-bottom: 18px;
    line-height: 1;
}
.blocky-ov-title {
    font-size: 22px;
    font-weight: 700;
    margin: 0 0 10px;
    color: #1e1e1e;
}
.blocky-ov-desc {
    color: #555d66;
    margin: 0 0 28px;
    font-size: 14px;
    line-height: 1.6;
}
.blocky-ov-btn {
    display: inline-block;
    background: #7c3aed;
    color: #fff !important;
    padding: 13px 32px;
    border-radius: 6px;
    text-decoration: none !important;
    font-size: 15px;
    font-weight: 600;
    transition: background .15s ease;
}
.blocky-ov-btn:hover,
.blocky-ov-btn:focus {
    background: #6d28d9;
    color: #fff !important;
    outline: 2px solid #7c3aed;
    outline-offset: 2px;
}
        ';
    }

    // ── General admin styles (list + meta box + admin bar) ───────────────────

    public function enqueueAdminStyles(string $hook): void
    {
        if (!\in_array($hook, ['post.php', 'post-new.php', 'edit.php'], true)) {
            return;
        }

        $css = '
/* ── Admin bar – Blocky Editor button ───────────────────────────── */
#wp-admin-bar-blocky-editor-link > a.ab-item {
    background: #7c3aed !important;
    color: #fff !important;
    font-weight: 600;
}
#wp-admin-bar-blocky-editor-link > a.ab-item:hover,
#wp-admin-bar-blocky-editor-link > a.ab-item:focus {
    background: #6d28d9 !important;
    color: #fff !important;
}
#wp-admin-bar-blocky-editor-link .ab-icon.dashicons {
    font-size: 18px;
    width: 18px;
    margin-right: 5px;
    vertical-align: middle;
    position: relative;
    top: 1px;
}

/* ── Post list – row action ──────────────────────────────────────── */
.row-actions span.blocky_editor a { color: #7c3aed; }
.row-actions span.blocky_editor a:hover { color: #6d28d9; text-decoration: underline; }

/* ── Post list – Built with Blocky badge ─────────────────────────── */
.blocky-built-badge {
    display: inline-block;
    padding: 1px 9px;
    border-radius: 999px;
    background: #7c3aed;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.6;
    vertical-align: middle;
    cursor: default;
}
td.blocky_built.column-blocky_built { width: 80px; text-align: center; }

/* ── Sidebar meta box ────────────────────────────────────────────── */
.blocky-metabox-btn {
    display: block !important;
    text-align: center;
    background: #7c3aed !important;
    color: #fff !important;
    border-color: #6d28d9 !important;
    padding: 8px 12px !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    width: 100%;
    box-sizing: border-box;
    height: auto !important;
}
.blocky-metabox-btn:hover,
.blocky-metabox-btn:focus {
    background: #6d28d9 !important;
    border-color: #5b21b6 !important;
    color: #fff !important;
}
.blocky-metabox-notice {
    margin: 0 0 10px;
    font-size: 12px;
    color: #646970;
}
        ';

        \wp_add_inline_style('common', $css);
    }
}