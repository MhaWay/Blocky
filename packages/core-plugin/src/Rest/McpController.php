<?php
/**
 * MCP-style JSON-RPC endpoint for agent-driven building.
 *
 * An MCP proxy terminates stdio and forwards JSON-RPC bodies here.
 * Every mutation flows through the same controllers, parser and
 * PropsValidator as the builder UI.
 *
 * @package Blocky\Core\Rest
 */

declare( strict_types=1 );

namespace Blocky\Core\Rest;

use Blocky\Core\Blocks\Registry;
use Blocky\Core\Support\ApiAudit;
use Blocky\Core\Support\ApiAuth;

/**
 * Serves blocky.* tools over one JSON-RPC route.
 */
final class McpController {

    private DocumentController $documents;

    private Registry $registry;

    public function __construct( DocumentController $documents, Registry $registry ) {
        $this->documents = $documents;
        $this->registry  = $registry;
    }

    public function register_routes(): void {
        register_rest_route(
            'blocky/v1',
            '/mcp',
            array(
                'methods' => 'POST',
                'callback' => array( $this, 'handle' ),
                'permission_callback' => static function (): bool {
                    return \current_user_can( 'edit_posts' ) || ApiAuth::current_key_row() !== null;
                },
            )
        );
    }

    public function handle( \WP_REST_Request $request ): \WP_REST_Response {
        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            return $this->error( null, -32600, 'Invalid request' );
        }

        $id   = $body['id'] ?? null;
        $name = (string) ( $body['method'] ?? '' );

        if ( 'initialize' === $name ) {
            return $this->result( $id, array(
                'protocolVersion' => '2024-11-05',
                'serverInfo' => array( 'name' => 'blocky', 'version' => BLOCKY_CORE_VERSION ),
                'capabilities' => array( 'tools' => array() ),
            ) );
        }

        if ( 'tools/list' === $name ) {
            return $this->result( $id, array( 'tools' => $this->tools() ) );
        }

        if ( 'tools/call' === $name ) {
            return $this->call( $id, $body );
        }

        return $this->error( $id, -32601, 'Unknown method' );
    }

    /**
     * Execute one tool call.
     *
     * @param mixed                $id   Request id.
     * @param array<string,mixed>  $body Raw body.
     * @return \WP_REST_Response Result envelope.
     */
    private function call( mixed $id, array $body ): \WP_REST_Response {
        $params = isset( $body['params'] ) && is_array( $body['params'] ) ? $body['params'] : array();
        $name   = (string) ( $params['name'] ?? '' );
        $args   = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

        $row    = ApiAuth::current_key_row();
        $key_id = $row ? (string) ( $row['public_id'] ?? '' ) : '';

        switch ( $name ) {
            case 'blocky_list_blocks':
                ApiAudit::log( $key_id, 'mcp.list_blocks', '' );
                return $this->result( $id, array(
                    'content' => array( array( 'type' => 'text', 'text' => (string) wp_json_encode( $this->catalog() ) ) ),
                ) );

            case 'blocky_get_document':
                ApiAudit::log( $key_id, 'mcp.get_document', (string) (int) ( $args['post_id'] ?? 0 ) );
                $inner = new \WP_REST_Request( 'GET', '/blocky/v1/documents/x' );
                $inner->set_param( 'post_id', (int) ( $args['post_id'] ?? 0 ) );
                return $this->forward( $id, $this->documents->getPostDocument( $inner ) );

            case 'blocky_save_document':
                ApiAudit::log( $key_id, 'mcp.save_document', (string) (int) ( $args['post_id'] ?? 0 ) );
                $inner = new \WP_REST_Request( 'POST', '/blocky/v1/documents/x' );
                $inner->set_param( 'post_id', (int) ( $args['post_id'] ?? 0 ) );
                $inner->set_param( 'document', $args['document'] ?? null );
                return $this->forward( $id, $this->documents->savePostDocument( $inner ) );

            default:
                return $this->error( $id, -32602, 'Unknown tool' );
        }
    }

    /**
     * Serialize the block catalog.
     *
     * @return array<string, array<string, mixed>> Catalog.
     */
    private function catalog(): array {
        $out = array();
        foreach ( $this->registry->all() as $type => $def ) {
            $out[ $type ] = array(
                'label'    => $def->label,
                'category' => $def->category,
                'schema'   => $def->schema,
                'variants' => array_keys( $def->variants ),
            );
        }
        return $out;
    }

    /**
     * Tool descriptors for tools/list.
     *
     * @return array<int, array<string, mixed>> Descriptors.
     */
    private function tools(): array {
        return array(
            array(
                'name' => 'blocky_list_blocks',
                'description' => 'List block types with props schemas (closed-set contract).',
                'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
            ),
            array(
                'name' => 'blocky_get_document',
                'description' => 'Read a post BuilderDoc.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array( 'post_id' => array( 'type' => 'integer' ) ),
                    'required' => array( 'post_id' ),
                ),
            ),
            array(
                'name' => 'blocky_save_document',
                'description' => 'Validate and save a BuilderDoc; server enforces the registry contract.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'post_id' => array( 'type' => 'integer' ),
                        'document' => array( 'type' => 'object' ),
                    ),
                    'required' => array( 'post_id', 'document' ),
                ),
            ),
        );
    }

    /**
     * Wrap an inner controller result.
     *
     * @param mixed $id    Request id.
     * @param mixed $inner Inner result.
     * @return \WP_REST_Response Envelope.
     */
    private function forward( mixed $id, mixed $inner ): \WP_REST_Response {
        if ( $inner instanceof \WP_REST_Response ) {
            $data = $inner->get_data();
        } elseif ( $inner instanceof \WP_Error ) {
            $data = array(
                'error'   => $inner->get_error_code(),
                'message' => $inner->get_error_message(),
                'details' => $inner->get_error_data(),
            );
        } else {
            $data = array( 'error' => 'failed' );
        }
        return $this->result( $id, array( 'content' => array( array( 'type' => 'text', 'text' => (string) wp_json_encode( $data ) ) ) ) );
    }

    /**
     * Success envelope.
     *
     * @param mixed               $id      Request id.
     * @param array<string,mixed> $payload Payload.
     * @return \WP_REST_Response Envelope.
     */
    private function result( mixed $id, array $payload ): \WP_REST_Response {
        return rest_ensure_response( array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $payload ) );
    }

    /**
     * Error envelope.
     *
     * @param mixed  $id      Request id.
     * @param int    $code    Error code.
     * @param string $message Error text.
     * @return \WP_REST_Response Envelope.
     */
    private function error( mixed $id, int $code, string $message ): \WP_REST_Response {
        return rest_ensure_response( array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => $code, 'message' => $message ) ) );
    }
}

