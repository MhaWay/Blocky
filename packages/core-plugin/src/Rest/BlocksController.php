<?php
declare(strict_types=1);

namespace Blocky\Core\Rest;

use Blocky\Core\Blocks\Registry;

/**
 * REST controller: /blocky/v1/blocks
 * Returns registered block definitions for the builder SPA.
 */
final class BlocksController extends \WP_REST_Controller
{
    protected $namespace = 'blocky/v1';
    protected $rest_base = 'blocks';

    public function __construct(
        private readonly Registry $registry,
    ) {}

    public function register_routes(): void // phpcs:ignore
    {
        \register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItems'],
                'permission_callback' => fn() => \Blocky\Core\Support\Access::allowed('catalog:read', 'edit_posts'),
            ],
        ]);

        \register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<type>[a-z0-9\/\-]+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItem'],
                'permission_callback' => fn() => \Blocky\Core\Support\Access::allowed('catalog:read', 'edit_posts'),
                'args'                => [
                    'type' => ['required' => true, 'type' => 'string'],
                ],
            ],
        ]);
    }

    public function getItems(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = [];
        foreach ($this->registry->all() as $type => $def) {
            $result[] = [
                'type'         => $def->type,
                'label'        => $def->label,
                'category'     => $def->category,
                'description'  => $def->description,
                'keywords'     => $def->keywords,
                'icon'         => $def->icon,
                'schema'       => $def->schema,
                'variants'     => $def->variants,
                'editorConfig' => $def->editorConfig,
                'interactive'  => $def->interactive,
            ];
        }
        return \rest_ensure_response($result);
    }

    public function getItem(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $type = $request->get_param('type');
        $def  = $this->registry->get($type);

        if ($def === null) {
            return new \WP_Error('not_found', \__('Block type not found.', 'blocky'), ['status' => 404]);
        }

        return \rest_ensure_response([
            'type'         => $def->type,
            'label'        => $def->label,
            'category'     => $def->category,
            'description'  => $def->description,
            'keywords'     => $def->keywords,
            'icon'         => $def->icon,
            'schema'       => $def->schema,
            'variants'     => $def->variants,
            'editorConfig' => $def->editorConfig,
            'interactive'  => $def->interactive,
        ]);
    }
}
