<?php
declare(strict_types=1);

namespace Blocky\Core\Rest;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Registry;
use Blocky\Core\Blocks\Renderer\Pipeline;
use Blocky\Core\Compiler\PageCompiler;
use Blocky\Core\Support\CssSanitizer;
use Blocky\Core\Support\PropsValidator;
use Blocky\Core\Tokens\ThemeEngine;

/**
 * REST controller: /blocky/v1/documents
 * Handles server-side rendering of builder documents.
 */
final class DocumentController extends \WP_REST_Controller
{
    /**
     * Assignment marker: which editor OWNS this document. Set when Blocky
     * creates the page or saves its document; drives the admin 'Built with
     * Blocky' badge and the primary 'Edit with Blocky' row action.
     */
    public const EDITOR_META_KEY = '_blocky_editor';

    /**
     * Assignment marker for reusable templates: value is the template
     * kind ('header', 'footer', 'single-post', ...). Empty means the
     * document is a regular page.
     */
    public const TEMPLATE_META_KEY = '_blocky_template';

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
                'permission_callback' => fn() => \Blocky\Core\Support\Access::allowed('documents:read', 'edit_posts'),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'createLibraryPage'],
                'permission_callback' => fn() => \Blocky\Core\Support\Access::allowed('documents:write', 'edit_pages'),
                'args'                => [
                    'title' => ['required' => false, 'type' => 'string'],
                ],
            ],
        ]);

        \register_rest_route($this->namespace, '/' . $this->rest_base . '/library/(?P<post_id>\d+)', [
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deleteLibraryPage'],
                'permission_callback' => fn() => \Blocky\Core\Support\Access::allowed('documents:write', 'delete_pages'),
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
                'permission_callback' => fn() => \Blocky\Core\Support\Access::allowed('documents:read', 'edit_posts'),
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
                'permission_callback' => fn() => \Blocky\Core\Support\Access::allowed('documents:read', 'edit_posts'),
                'args'                => [
                    'post_id' => ['required' => true, 'type' => 'integer'],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'savePostDocument'],
                'permission_callback' => fn() => \Blocky\Core\Support\Access::allowed('documents:write', 'edit_posts'),
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
                'permission_callback' => fn() => \Blocky\Core\Support\Access::allowed('documents:write', 'edit_posts'),
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
                'permission_callback' => fn() => \Blocky\Core\Support\Access::allowed('documents:read', 'edit_posts'),
                'args'                => [
                    'post_id' => ['required' => true, 'type' => 'integer'],
                    'title'   => ['required' => false, 'type' => 'string'],
                    'status'  => ['required' => false, 'type' => 'string'],
                ],
            ],
        ]);
    }

    /**
     * Read the `document` parameter from the raw JSON body.
     *
     * WP_REST_Request unslashes the whole body before json_decode, which
     * silently rewrites escaped characters inside string values (`\n` turns
     * into `n`). Decoding the raw body keeps multi-line props verbatim.
     * Falls back to get_param() for form-encoded requests, where the body
     * legitimately arrives slashed.
     */
    private static function document_param(\WP_REST_Request $request): mixed
    {
        $body    = (string) $request->get_body();
        $decoded = $body !== '' ? json_decode($body, true) : null;

        if (is_array($decoded) && array_key_exists('document', $decoded)) {
            return $decoded['document'];
        }

        return $request->get_param('document');
    }

    public function render(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $document = self::document_param($request);
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

        if (!\Blocky\Core\Support\Access::allowed_post('documents:read', 'edit_post', $postId)) {
            return new \WP_Error('rest_forbidden', \__('You are not allowed to edit this post.', 'blocky'), ['status' => 403]);
        }

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
        $document = self::document_param($request);

        if (!\Blocky\Core\Support\Access::allowed_post('documents:write', 'edit_post', $postId)) {
            return new \WP_Error('forbidden', \__('You cannot edit this post.', 'blocky'), ['status' => 403]);
        }

        $json = \wp_json_encode($document);
        if ($json === false) {
            return new \WP_Error('invalid_document', \__('Invalid document structure.', 'blocky'), ['status' => 400]);
        }

        $errors = (new PropsValidator($this->registry))->validate(is_array($document) ? $document : []);
        if ($errors !== []) {
            return new \WP_Error(
                'invalid_props',
                \implode(\PHP_EOL, $errors),
                ['status' => 400, 'errors' => $errors]
            );
        }

        // WP's meta read path unslashes by convention; store slashed so
        // JSON escapes (\n, \") survive the round-trip intact.
        \update_post_meta($postId, '_blocky_document', \wp_slash($json));

        // Application rule: saving a document assigns the page to Blocky.
        \update_post_meta($postId, self::EDITOR_META_KEY, 'blocky');

        // The Gutenberg metabox entry creates auto-drafts; promote on first
        // save so the page surfaces in the Pages list and the builder library.
        $savedPost = \get_post($postId);
        if ($savedPost instanceof \WP_Post && $savedPost->post_status === 'auto-draft') {
            \wp_update_post(['ID' => $postId, 'post_status' => 'draft'], false);
        }
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

        if (!\Blocky\Core\Support\Access::allowed_post('documents:write', 'edit_post', $postId)) {
            return new \WP_Error('forbidden', \__('You cannot edit this post.', 'blocky'), ['status' => 403]);
        }

        // Same raw-body read as document_param: escaped newlines must survive.
        $body_raw  = (string) $request->get_body();
        $body_json = $body_raw !== '' ? json_decode($body_raw, true) : null;
        $css       = is_array($body_json) && array_key_exists('css', $body_json)
            ? (string) $body_json['css']
            : (string) $request->get_param('css');
        $css       = CssSanitizer::sanitize($css);
        $this->pageCompiler->cacheCompiledCss($postId, $css);

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

        // Created pages belong to Blocky from birth.
        \update_post_meta((int) $postId, self::EDITOR_META_KEY, 'blocky');

        // Template starters record their kind so the builder Templates tab
        // can manage them; regular pages keep the meta empty.
        $starter = \sanitize_key((string) ($request->get_param('starter') ?? 'page'));
        $templateKinds = ['base-template', 'header', 'footer', 'menu', 'sidebar', 'single-post', 'component'];
        if (\in_array($starter, $templateKinds, true)) {
            \update_post_meta((int) $postId, self::TEMPLATE_META_KEY, $starter);
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

        if (!\Blocky\Core\Support\Access::allowed_post('documents:write', 'delete_post', $postId)) {
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

        if (!\Blocky\Core\Support\Access::allowed_post('documents:write', 'edit_post', $postId)) {
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
            // mysql2date(DATE_ATOM, ..., false) emits epoch garbage; emit real ISO-8601 UTC.
            'modified'    => \gmdate(DATE_ATOM, \strtotime((string) ($post->post_modified_gmt ?: $post->post_modified)) ?: \time()),
            'hasDocument' => \get_post_meta($post->ID, '_blocky_document', true) !== '',
            'templateKind' => (string) \get_post_meta($post->ID, self::TEMPLATE_META_KEY, true),
        ];
    }
}
