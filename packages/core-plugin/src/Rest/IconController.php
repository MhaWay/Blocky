<?php
/**
 * REST: bundled icon list + sanitized user SVG icon uploads.
 *
 * @package Blocky\Core\Rest
 */

declare( strict_types=1 );

namespace Blocky\Core\Rest;

defined( 'ABSPATH' ) || exit; // Protect against direct file access;

use Blocky\Core\Support\Access;
use Blocky\Core\Support\IconLibrary;

/**
 * Icon endpoints consumed by the builder icon picker.
 */
final class IconController {

    /** @var 'blocky/v1' */
    protected $namespace = 'blocky/v1';

    /** @var 'icons' */
    protected $rest_base = 'icons';

    public function register_routes(): void
    {
        \register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'             => 'GET',
            'callback'            => [$this, 'listIcons'],
            'permission_callback' => static fn(): bool => Access::allowed('icons:read', 'edit_posts'),
        ]);
        \register_rest_route($this->namespace, '/' . $this->rest_base . '/upload', [
            'methods'             => 'POST',
            'callback'            => [$this, 'uploadIcon'],
            'permission_callback' => static fn(): bool => Access::allowed('icons:write', 'upload_files'),
            'args'                => [
                'svg'  => ['required' => true, 'type' => 'string'],
                'name' => ['required' => false, 'type' => 'string'],
            ],
        ]);
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function listIcons(\WP_REST_Request $request)
    {
        return \rest_ensure_response(['icons' => IconLibrary::all()]);
    }

    /**
     * Stores a sanitized inline SVG as an attachment and returns its token.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function uploadIcon(\WP_REST_Request $request)
    {
        $safe = IconLibrary::sanitize_svg((string) $request->get_param('svg'));
        if (null === $safe) {
            return new \WP_Error('blocky_invalid_svg', \__('The SVG could not be sanitized.', 'blocky'), ['status' => 400]);
        }

        $name  = sanitize_file_name((string) ($request->get_param('name') ?: 'icon'));

        // The bytes about to be stored are already sanitized; allow this one
        // SVG write even on sites where core disables SVG uploads by default.
        $allow_svg = static fn(array $mimes): array => array_merge($mimes, ['svg' => 'image/svg+xml']);
        $fix_type = static function ($checked, $file, $filename) {
            if ('svg' === strtolower((string) pathinfo((string) $filename, PATHINFO_EXTENSION))) {
                $checked['ext'] = 'svg';
                $checked['type'] = 'image/svg+xml';
            }
            return $checked;
        };
        \add_filter('upload_mimes', $allow_svg, 20);
        \add_filter('wp_check_filetype_and_ext', $fix_type, 10, 3);
        $upload = \wp_upload_bits('blocky-' . $name . '.svg', null, $safe);
        \remove_filter('upload_mimes', $allow_svg, 20);
        \remove_filter('wp_check_filetype_and_ext', $fix_type, 10);
        if (false !== $upload['error']) {
            return new \WP_Error('blocky_upload_failed', \__('The icon could not be stored.', 'blocky'), ['status' => 500]);
        }

        $attachment_id = (int) \wp_insert_attachment(
            [
                'post_mime_type' => 'image/svg+xml',
                'post_title'     => sanitize_text_field($name),
                'post_status'    => 'inherit',
            ],
            $upload['file']
        );
        if ($attachment_id <= 0) {
            return new \WP_Error('blocky_upload_failed', \__('The icon could not be stored.', 'blocky'), ['status' => 500]);
        }

        \update_post_meta($attachment_id, IconLibrary::UPLOAD_META_KEY, $safe);
        \update_attached_file($attachment_id, $upload['file']);

        return \rest_ensure_response(['token' => 'upload-' . $attachment_id]);
    }
}
