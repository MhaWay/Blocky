<?php
declare(strict_types=1);

namespace Blocky\Core\Rest;

use Blocky\Core\Blocks\Registry;
use Blocky\Core\Blocks\Renderer\Pipeline;
use Blocky\Core\Compiler\PageCompiler;
use Blocky\Core\Compiler\SiteStylesheet;
use Blocky\Core\Support\CssSanitizer;
use Blocky\Core\Tokens\ThemeEngine;

/**
 * REST controller: /blocky/v1/documents
 * Handles server-side rendering of builder documents.
 */
final class DocumentController extends \WP_REST_Controller
{
    protected $namespace = 'blocky/v1';
    protected $rest_base = 'documents';

    private Pipeline $pipeline;

    public function __construct(
        private readonly Registry    $registry,
        private readonly ThemeEngine $themeEngine,
        private readonly PageCompiler $pageCompiler,
    ) {
        $this->pipeline = new Pipeline($this->registry, $this->themeEngine);
    }

    public function register_routes(): void // phpcs:ignore
    {
        \register_rest_route($this->namespace, '/' . $this->rest_base . '/library', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'listLibraryPages'],
                'permission_callback' => fn() => \current_user_can('edit_posts'),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'createLibraryPage'],
                'permission_callback' => fn() => \current_user_can('edit_posts'),
                'args'                => [
                    'title' => ['required' => false, 'type' => 'string'],
                ],
            ],
        ]);

        \register_rest_route($this->namespace, '/' . $this->rest_base . '/library/(?P<post_id>\d+)', [
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deleteLibraryPage'],
                'permission_callback' => fn() => \current_user_can('delete_posts'),
                'args'                => [
                    'post_id' => ['required' => true, 'type' => 'integer'],
                ],
            ],
        ]);

        // Render a document JSON body → HTML
        \register_rest_route($this->namespace, '/' . $this->rest_base . '/render', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'render'],
                'permission_callback' => fn() => \current_user_can('edit_posts'),
                'args'                => [
                    'document' => [
                        'required'          => true,
                        'type'              => 'object',
                        'validate_callback' => [$this, 'validateDocument'],
                    ],
                ],
            ],
        ]);

        // Get rendered HTML for a post's Blocky document
        \register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<post_id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getPostDocument'],
                'permission_callback' => fn() => \current_user_can('edit_posts'),
                'args'                => [
                    'post_id' => ['required' => true, 'type' => 'integer'],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'savePostDocument'],
                'permission_callback' => fn() => \current_user_can('edit_posts'),
                'args'                => [
                    'post_id'  => ['required' => true, 'type' => 'integer'],
                    'document' => ['required' => true, 'type' => 'object'],
                ],
            ],
        ]);

        \register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<post_id>\d+)/css', [
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'savePostCss'],
                'permission_callback' => fn() => \current_user_can('edit_posts'),
                'args'                => [
                    'post_id' => ['required' => true, 'type' => 'integer'],
                    'css'     => ['required' => true, 'type' => 'string'],
                ],
            ],
        ]);

        \register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<post_id>\d+)/details', [
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'updatePostDetails'],
                'permission_callback' => fn() => \current_user_can('edit_posts'),
                'args'                => [
                    'post_id' => ['required' => true, 'type' => 'integer'],
                    'title'   => ['required' => false, 'type' => 'string'],
                    'status'  => ['required' => false, 'type' => 'string'],
                ],
            ],
        ]);
    }

    public function render(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $document = $request->get_param('document');
        $json     = \wp_json_encode($document);

        if ($json === false) {
            return new \WP_Error('invalid_document', \__('Invalid document structure.', 'blocky'), ['status' => 400]);
        }

        $html = $this->pipeline->renderDocument($json, true);

        return \rest_ensure_response(['html' => $html->toString()]);
    }

    public function getPostDocument(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $postId   = (int) $request->get_param('post_id');
        $post     = \get_post($postId);

        if ($post === null) {
            return new \WP_Error('not_found', \__('Post not found.', 'blocky'), ['status' => 404]);
        }

        $raw = \get_post_meta($postId, '_blocky_document', true);

        if (!\is_string($raw) || $raw === '') {
            return \rest_ensure_response([
                'document' => null,
                'html'     => '',
                'post'     => $this->postPayload($post),
            ]);
        }

        $html = $this->pipeline->renderDocument($raw, true);

        return \rest_ensure_response([
            'document' => json_decode($raw, true),
            'html'     => $html->toString(),
            'post'     => $this->postPayload($post),
        ]);
    }

    public function savePostDocument(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $postId   = (int) $request->get_param('post_id');
        $document = $request->get_param('document');

        if (!\current_user_can('edit_post', $postId)) {
            return new \WP_Error('forbidden', \__('You cannot edit this post.', 'blocky'), ['status' => 403]);
        }

        $json = \wp_json_encode($document);
        if ($json === false) {
            return new \WP_Error('invalid_document', \__('Invalid document structure.', 'blocky'), ['status' => 400]);
        }

        \update_post_meta($postId, '_blocky_document', $json);
        $compiled = $this->pageCompiler->warmFrontendCache($postId, $json);

        $html = $this->pipeline->renderDocument($json, true);

        return \rest_ensure_response([
            'html'            => $html->toString(),
            'classCandidates' => $compiled['classCandidates'],
            'compileHash'     => $compiled['hash'],
        ]);
    }

    public function savePostCss(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $postId = (int) $request->get_param('post_id');

        if (!\current_user_can('edit_post', $postId)) {
            return new \WP_Error('forbidden', \__('You cannot edit this post.', 'blocky'), ['status' => 403]);
        }

        $css = CssSanitizer::sanitize((string) $request->get_param('css'));
        $this->pageCompiler->cacheCompiledCss($postId, $css);
        SiteStylesheet::schedule_rebuild();

        return \rest_ensure_response([
            'saved' => true,
        ]);
    }

    public function listLibraryPages(\WP_REST_Request $request): \WP_REST_Response
    {
        $posts = \get_posts([
            'post_type'      => 'page',
            'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'posts_per_page' => 100,
        ]);

        return \rest_ensure_response([
            'items' => array_map(fn(\WP_Post $post) => $this->postPayload($post), $posts),
        ]);
    }

    public function createLibraryPage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $title = \sanitize_text_field((string) ($request->get_param('title') ?? 'New Blocky Page'));
        if ($title === '') {
            $title = 'New Blocky Page';
        }

        $postId = \wp_insert_post([
            'post_type'   => 'page',
            'post_status' => 'draft',
            'post_title'  => $title,
        ], true);

        if ($postId instanceof \WP_Error) {
            return $postId;
        }

        $post = \get_post((int) $postId);
        if ($post === null) {
            return new \WP_Error('not_found', \__('Created page could not be loaded.', 'blocky'), ['status' => 500]);
        }

        return \rest_ensure_response([
            'post' => $this->postPayload($post),
        ]);
    }

    public function deleteLibraryPage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $postId = (int) $request->get_param('post_id');
        $post = \get_post($postId);

        if ($post === null || $post->post_type !== 'page') {
            return new \WP_Error('not_found', \__('Page not found.', 'blocky'), ['status' => 404]);
        }

        if (!\current_user_can('delete_post', $postId)) {
            return new \WP_Error('forbidden', \__('You cannot delete this page.', 'blocky'), ['status' => 403]);
        }

        $trashed = \wp_trash_post($postId);
        if ($trashed === false || $trashed === null) {
            return new \WP_Error('delete_failed', \__('The page could not be moved to trash.', 'blocky'), ['status' => 500]);
        }

        return \rest_ensure_response([
            'deleted' => true,
            'postId'  => $postId,
        ]);
    }

    public function updatePostDetails(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $postId = (int) $request->get_param('post_id');
        $post = \get_post($postId);

        if ($post === null) {
            return new \WP_Error('not_found', \__('Post not found.', 'blocky'), ['status' => 404]);
        }

        if (!\current_user_can('edit_post', $postId)) {
            return new \WP_Error('forbidden', \__('You cannot edit this post.', 'blocky'), ['status' => 403]);
        }

        $updates = ['ID' => $postId];

        if ($request->has_param('title')) {
            $title = \sanitize_text_field((string) $request->get_param('title'));
            if ($title === '') {
                return new \WP_Error('invalid_title', \__('Title cannot be empty.', 'blocky'), ['status' => 400]);
            }

            $updates['post_title'] = $title;
        }

        if ($request->has_param('status')) {
            $status = \sanitize_key((string) $request->get_param('status'));
            if (!\in_array($status, ['draft', 'publish'], true)) {
                return new \WP_Error('invalid_status', \__('Unsupported post status.', 'blocky'), ['status' => 400]);
            }

            if ($status === 'publish') {
                $postTypeObject = \get_post_type_object($post->post_type);
                $publishCapability = $postTypeObject?->cap->publish_posts ?? 'publish_posts';
                if (!\current_user_can($publishCapability)) {
                    return new \WP_Error('forbidden', \__('You cannot publish this post.', 'blocky'), ['status' => 403]);
                }
            }

            $updates['post_status'] = $status;
        }

        if (count($updates) === 1) {
            return new \WP_Error('invalid_request', \__('Nothing to update.', 'blocky'), ['status' => 400]);
        }

        $updated = \wp_update_post($updates, true);

        if ($updated instanceof \WP_Error) {
            return $updated;
        }

        $nextPost = \get_post($postId);
        if ($nextPost === null) {
            return new \WP_Error('not_found', \__('Updated post could not be loaded.', 'blocky'), ['status' => 500]);
        }

        return \rest_ensure_response([
            'post' => $this->postPayload($nextPost),
        ]);
    }

    /**
     * @param mixed $value
     */
    public function validateDocument(mixed $value): bool|\WP_Error
    {
        if (!\is_array($value)) {
            return new \WP_Error('invalid_document', \__('Document must be an object.', 'blocky'));
        }
        if (!isset($value['root'], $value['nodes'])) {
            return new \WP_Error('invalid_document', \__('Document must have root and nodes keys.', 'blocky'));
        }
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function postPayload(\WP_Post $post): array
    {
        $title = \get_the_title($post);
        $isPublished = $post->post_status === 'publish';
        $link = $isPublished ? \get_permalink($post) : \get_preview_post_link($post);

        return [
            'id'          => $post->ID,
            'title'       => $title !== '' ? $title : '(Untitled)',
            'status'      => $post->post_status,
            'type'        => $post->post_type,
            'link'        => \is_string($link) ? $link : '',
            'modified'    => \mysql2date(DATE_ATOM, $post->post_modified_gmt !== '' ? $post->post_modified_gmt : $post->post_modified, false),
            'hasDocument' => \get_post_meta($post->ID, '_blocky_document', true) !== '',
        ];
    }
}
