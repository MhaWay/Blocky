<?php
/**
 * REST: bundled and user-saved reusable section patterns.
 *
 * @package Blocky\Core\Rest
 */

declare( strict_types=1 );

namespace Blocky\Core\Rest;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Registry;
use Blocky\Core\Support\Access;

/**
 * Patterns are validated node subtrees stored as deterministic JSON.
 * Bundled patterns ship with the plugin; user patterns live in one option.
 * Insertion happens client-side and the resulting document goes through the
 * regular validated save pipeline.
 */
final class PatternsController {

    public const OPTION_KEY = 'blocky_user_patterns';

    /** @var 'blocky/v1' */
    protected $namespace = 'blocky/v1';

    /** @var 'patterns' */
    protected $rest_base = 'patterns';

    public function __construct(
        private readonly Registry $registry,
    ) {}

    public function register_routes(): void
    {
        \register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'listPatterns'],
                'permission_callback' => static fn(): bool => Access::allowed('documents:read', 'edit_posts'),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'savePattern'],
                'permission_callback' => static fn(): bool => Access::allowed('documents:write', 'manage_options'),
                'args'                => [
                    'title' => ['required' => true, 'type' => 'string'],
                    'nodes' => ['required' => true, 'type' => 'object'],
                    'root'  => ['required' => true, 'type' => 'string'],
                ],
            ],
        ]);
        \register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[a-z0-9-]+)', [
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'deletePattern'],
                'permission_callback' => static fn(): bool => Access::allowed('documents:write', 'manage_options'),
            ],
        ]);
    }

    /**
     * @return \WP_REST_Response
     */
    public function listPatterns(\WP_REST_Request $request)
    {
        $patterns = self::bundled();
        foreach (self::user_patterns() as $id => $pattern) {
            $patterns[] = $pattern + array('id' => $id);
        }
        return \rest_ensure_response(array('patterns' => $patterns));
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function savePattern(\WP_REST_Request $request)
    {
        $title = sanitize_text_field((string) $request->get_param('title'));
        if ('' === $title) {
            return new \WP_Error('blocky_pattern_title', \__('The pattern needs a title.', 'blocky'), array('status' => 400));
        }

        $candidates = array('root' => (string) $request->get_param('root'), 'nodes' => $request->get_param('nodes'));
        if (strlen((string) wp_json_encode($candidates)) > 400000) {
            return new \WP_Error('blocky_pattern_too_large', \__('The pattern is too large.', 'blocky'), array('status' => 400));
        }

        $clean = self::sanitize_subtree((array) $candidates['nodes'], (string) $candidates['root'], $this->registry);
        if (null === $clean) {
            return new \WP_Error('blocky_pattern_invalid', \__('The pattern contains unknown blocks or references.', 'blocky'), array('status' => 400));
        }

        $stored   = self::user_patterns();
        $new_id   = 'user-' . substr(md5($title . microtime()), 0, 8);
        $stored[$new_id] = array(
            'title'    => $title,
            'category' => 'user',
            'root'     => $clean['root'],
            'nodes'    => $clean['nodes'],
        );
        if (count($stored) > 30) {
            array_shift($stored);
        }
        \update_option(self::OPTION_KEY, $stored, false);

        return \rest_ensure_response(array('pattern' => $stored[$new_id] + array('id' => $new_id)));
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function deletePattern(\WP_REST_Request $request)
    {
        $id      = (string) $request->get_param('id');
        $stored  = self::user_patterns();
        if (!isset($stored[$id])) {
            return new \WP_Error('blocky_pattern_missing', \__('Only saved patterns can be deleted.', 'blocky'), array('status' => 404));
        }
        unset($stored[$id]);
        \update_option(self::OPTION_KEY, $stored, false);
        return \rest_ensure_response(array('deleted' => $id));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function bundled(): array
    {
        $out = array();
        $dir = BLOCKY_CORE_DIR . 'templates/patterns';
        foreach (glob($dir . '/*.json') ?: array() as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data) && isset($data['id'], $data['title'], $data['nodes'], $data['root'])) {
                $out[] = $data;
            }
        }
        return $out;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function user_patterns(): array
    {
        $stored = \get_option(self::OPTION_KEY, array());
        return is_array($stored) ? $stored : array();
    }

    /**
     * Validates a node map: every type must exist, every slot child must resolve.
     *
     * @param mixed $nodes
     * @return array{root: string, nodes: array<string, mixed>}|null
     */
    private static function sanitize_subtree($nodes, string $root, Registry $registry): ?array
    {
        if (!is_array($nodes) || '' === $root || !isset($nodes[$root]) || count($nodes) > 400) {
            return null;
        }
        $clean = array();
        foreach ($nodes as $id => $node) {
            if (!is_string($id) || !is_array($node)) {
                return null;
            }
            $type = (string) ($node['type'] ?? '');
            if ('' === $type || !$registry->has($type)) {
                return null;
            }
            $props = array();
            foreach ((array) ($node['props'] ?? array()) as $key => $value) {
                if (is_string($key) && (is_scalar($value) || is_array($value))) {
                    $props[$key] = $value;
                }
            }
            $slots = array();
            foreach ((array) ($node['slots'] ?? array()) as $slot => $children) {
                $slot = (string) $slot;
                foreach ((array) $children as $child) {
                    if (is_string($child) && isset($nodes[$child])) {
                        $slots[$slot][] = $child;
                    }
                }
            }
            $clean[(string) $id] = array(
                'id'       => (string) $id,
                'type'     => $type,
                'props'    => $props,
                'slots'    => $slots,
                'variants' => array(),
            );
        }
        return array('root' => $root, 'nodes' => $clean);
    }
}
