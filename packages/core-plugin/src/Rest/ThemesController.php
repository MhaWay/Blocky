<?php
declare(strict_types=1);

namespace Blocky\Core\Rest;

use Blocky\Core\Tokens\ThemeEngine;
use Blocky\Core\Tokens\ThemeSettings;

/**
 * REST controller: /blocky/v1/themes
 */
final class ThemesController extends \WP_REST_Controller
{
    protected $namespace = 'blocky/v1';
    protected $rest_base = 'themes';

    public function __construct(
        private readonly ThemeEngine $themeEngine,
    ) {}

    public function register_routes(): void // phpcs:ignore
    {
        \register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItems'],
                'permission_callback' => '__return_true',
            ],
        ]);

        \register_rest_route($this->namespace, '/' . $this->rest_base . '/active', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getActive'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'setActive'],
                'permission_callback' => fn() => \current_user_can('edit_posts'),
                'args'                => [
                    'brand' => ['type' => 'string', 'pattern' => '^[a-z0-9\-]{1,64}$'],
                    'mode'  => ['type' => 'string', 'enum' => ['light', 'dark']],
                ],
            ],
        ]);

        \register_rest_route($this->namespace, '/' . $this->rest_base . '/tokens', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getTokens'],
                'permission_callback' => fn() => \current_user_can('edit_theme_options'),
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'setTokens'],
                'permission_callback' => fn() => \current_user_can('edit_theme_options'),
                'args'                => [
                    'overrides' => ['required' => true, 'type' => 'object'],
                ],
            ],
        ]);
    }

    public function getItems(\WP_REST_Request $request): \WP_REST_Response
    {
        return \rest_ensure_response(array_values($this->themeEngine->getVariantsForJs()));
    }

    public function getActive(\WP_REST_Request $request): \WP_REST_Response
    {
        return \rest_ensure_response([
            'brand'     => $this->themeEngine->getActiveBrand(),
            'mode'      => $this->themeEngine->getActiveMode(),
            'variantId' => $this->themeEngine->getActiveVariantId(),
        ]);
    }

    public function setActive(\WP_REST_Request $request): \WP_REST_Response
    {
        // Theme preference is cookie-driven client-side; this endpoint for API completeness
        return \rest_ensure_response([
            'brand' => $request->get_param('brand') ?? $this->themeEngine->getActiveBrand(),
            'mode'  => $request->get_param('mode')  ?? $this->themeEngine->getActiveMode(),
        ]);
    }

    public function getTokens(\WP_REST_Request $request): \WP_REST_Response
    {
        return \rest_ensure_response(ThemeSettings::settingsForRest());
    }

    public function setTokens(\WP_REST_Request $request): \WP_REST_Response
    {
        $overrides = $request->get_param('overrides');
        if (!\is_array($overrides)) {
            $overrides = [];
        }

        ThemeSettings::saveOverrides($overrides);

        return \rest_ensure_response(ThemeSettings::settingsForRest());
    }
}
