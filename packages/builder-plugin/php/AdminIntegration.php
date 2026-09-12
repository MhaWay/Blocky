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

        $url  = \esc_url(\admin_url('admin.php?page=blocky-builder&post_id=' . $post->ID));
        $link = '<a href="' . $url . '">' . \esc_html__('Blocky Editor', 'blocky') . '</a>';

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
                     . \esc_html__('Blocky Editor', 'blocky'),
            'href'  => \esc_url(\admin_url('admin.php?page=blocky-builder&post_id=' . $postId)),
            'meta'  => [
                'class' => 'blocky-adminbar-btn',
                'title' => \__('Edit with Blocky Builder', 'blocky'),
            ],
        ]);
    }

    // ── Sidebar meta box ─────────────────────────────────────────────────────

    public function registerMetaBox(): void
    {
        \add_meta_box(
            'blocky-editor-metabox',
            \__('Blocky Editor', 'blocky'),
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
            ? \__('Continue in Blocky Editor', 'blocky')
            : \__('Open in Blocky Editor', 'blocky');

        if ($hasDocument) {
            echo '<p class="blocky-metabox-notice">'
               . \esc_html__('This page is edited with Blocky Builder.', 'blocky')
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
                'title'  => \__('Blocky Editor', 'blocky'),
                'notice' => \__('This page is edited with Blocky Builder.', 'blocky'),
                'cta'    => \__('Continue in Blocky Editor', 'blocky'),
            ],
        ]);

        if ($hasDocument) {
            \wp_add_inline_script('wp-edit-post', $this->getOverlayScript(), 'after');
            \wp_add_inline_style('wp-edit-post', $this->getBlockEditorStyles());
        }
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
            overlay.setAttribute( 'aria-label', i18n.title || 'Blocky Editor' );

            overlay.innerHTML =
                '<div class="blocky-ov-inner">' +
                    '<span class="blocky-ov-logo" aria-hidden="true">&#9783;</span>' +
                    '<h2 class="blocky-ov-title">' + ( i18n.title  || 'Blocky Editor' ) + '</h2>' +
                    '<p  class="blocky-ov-desc">'  + ( i18n.notice || 'This page is edited with Blocky Builder.' ) + '</p>' +
                    '<a  class="blocky-ov-btn" href="' + data.builderUrl + '">' +
                        ( i18n.cta || 'Continue in Blocky Editor' ) +
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
