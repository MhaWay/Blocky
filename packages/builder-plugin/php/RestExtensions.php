<?php
declare(strict_types=1);

namespace Blocky\Builder;

use Blocky\Core\Blocks\Registry;
use Blocky\Core\Tokens\ThemeEngine;

/**
 * Extends the core REST API with builder-specific endpoints.
 */
final class RestExtensions
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct() {}

    public function boot(): void
    {
        \add_action('rest_api_init', [$this, 'register']);
    }

    public function register(): void
    {
        // POST /blocky/v1/builder/preview — inline preview HTML
        \register_rest_route('blocky/v1', '/builder/preview', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'preview'],
            'permission_callback' => fn() => \current_user_can('edit_posts'),
            'args'                => [
                'document' => ['required' => true, 'type' => 'object'],
                'postId'   => ['type' => 'integer'],
            ],
        ]);

        // GET /blocky/v1/builder/media — media library query helper
        \register_rest_route('blocky/v1', '/builder/media', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'queryMedia'],
            'permission_callback' => fn() => \current_user_can('upload_files'),
            'args'                => [
                'search'   => ['type' => 'string', 'default' => ''],
                'per_page' => ['type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
                'page'     => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
            ],
        ]);
    }

    public function preview(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $document = $request->get_param('document');
        $json     = \wp_json_encode($document);

        if ($json === false) {
            return new \WP_Error('invalid_document', \__('Invalid document.', 'blocky'), ['status' => 400]);
        }

        $registry = \Blocky\Core\Plugin::getInstance()->getRegistry();
        $engine   = \Blocky\Core\Plugin::getInstance()->getThemeEngine();
        $pipeline = new \Blocky\Core\Blocks\Renderer\Pipeline($registry, $engine);

        return \rest_ensure_response([
            'html' => $pipeline->renderDocument($json, true)->toString(),
        ]);
    }

    public function queryMedia(\WP_REST_Request $request): \WP_REST_Response
    {
        $search  = (string)  $request->get_param('search');
        $perPage = (int)     $request->get_param('per_page');
        $page    = (int)     $request->get_param('page');

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'post_mime_type' => 'image',
        ];

        if ($search !== '') {
            $args['s'] = \sanitize_text_field($search);
        }

        $query = new \WP_Query($args);
        $items = [];

        foreach ($query->posts as $post) {
            if (!($post instanceof \WP_Post)) {
                continue;
            }
            $items[] = [
                'id'       => $post->ID,
                'title'    => \get_the_title($post->ID),
                'url'      => \wp_get_attachment_url($post->ID),
                'thumb'    => \wp_get_attachment_image_url($post->ID, 'thumbnail'),
                'alt'      => \get_post_meta($post->ID, '_wp_attachment_image_alt', true),
                'mimeType' => $post->post_mime_type,
                'width'    => (int) (\wp_get_attachment_metadata($post->ID)['width']  ?? 0),
                'height'   => (int) (\wp_get_attachment_metadata($post->ID)['height'] ?? 0),
            ];
        }

        return \rest_ensure_response([
            'items' => $items,
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
        ]);
    }
}
