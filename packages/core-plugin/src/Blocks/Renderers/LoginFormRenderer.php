<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class LoginFormRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $postId = $ctx->currentPostId() > 0 ? $ctx->currentPostId() : self::resolveCurrentPostId();
        $formId = self::resolveFormId($node, $postId, 'login');
        $redirectUrl = trim((string) ($node->props['redirectUrl'] ?? ''));
        $title = trim((string) ($node->props['title'] ?? 'Welcome back'));
        $buttonLabel = trim((string) ($node->props['buttonLabel'] ?? 'Sign in'));
        $showRemember = (bool) ($node->props['showRemember'] ?? true);
        $showLostPassword = (bool) ($node->props['showLostPassword'] ?? true);
        $showRegisterLink = (bool) ($node->props['showRegisterLink'] ?? true);
        $classes = $ctx->resolveVariantClasses($node, [
            'gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6'],
            'padding' => ['none' => 'p-0', 'sm' => 'p-4', 'base' => 'p-6', 'lg' => 'p-8'],
            'background' => ['transparent' => '', 'surface' => 'bg-surface-base', 'elevated' => 'bg-surface-elevated'],
            'border' => ['none' => '', 'subtle' => 'border border-border-subtle', 'base' => 'border border-border-base'],
            'radius' => ['none' => 'rounded-none', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'xl' => 'rounded-xl'],
        ]);
        $gapClass = $ctx->resolveVariantClasses($node, ['gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6']]);

        if (!$ctx->isEditorMode() && is_user_logged_in()) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => trim('rounded-card border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 ' . $classes)]), esc_html__('You are already signed in.', 'blocky'));
        }

        $links = '<div class="flex flex-wrap justify-between gap-3 text-sm text-text-muted">'
            . ($showLostPassword ? '<a href="' . esc_url(wp_lostpassword_url()) . '" class="hover:text-accent-text">' . esc_html__('Lost your password?', 'blocky') . '</a>' : '')
            . ($showRegisterLink ? '<a href="' . esc_url(wp_registration_url()) . '" class="hover:text-accent-text">' . esc_html__('Create an account', 'blocky') . '</a>' : '')
            . '</div>';

        $html = ($ctx->isEditorMode() ? '' : self::renderStatus($formId))
            . ($title !== '' ? '<h3 class="text-lg font-semibold text-text-base">' . esc_html($title) . '</h3>' : '')
            . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="mt-4 flex flex-col ' . esc_attr($gapClass) . '">'
            . HtmlString::void('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'blocky_frontend_login_submit'])->toString()
            . HtmlString::void('input', ['type' => 'hidden', 'name' => '_blocky_login_submission', 'value' => '1'])->toString()
            . HtmlString::void('input', ['type' => 'hidden', 'name' => '_blocky_form_id', 'value' => $formId])->toString()
            . HtmlString::void('input', ['type' => 'hidden', 'name' => '_blocky_form_post_id', 'value' => $postId > 0 ? (string) $postId : ''])->toString()
            . HtmlString::void('input', ['type' => 'hidden', 'name' => '_blocky_login_redirect', 'value' => $redirectUrl])->toString()
            . wp_nonce_field('blocky_frontend_login_' . $formId, '_blocky_login_nonce', false, false)
            . self::field('Username or email', 'log', 'text', 'username')
            . self::field('Password', 'pwd', 'password', 'current-password')
            . ($showRemember ? '<label class="inline-flex items-center gap-3 text-sm text-text-base"><input type="checkbox" name="rememberme" value="forever" class="h-4 w-4 rounded border-border-base" /><span>' . esc_html__('Remember me', 'blocky') . '</span></label>' : '')
            . '<button type="submit" class="inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-3 text-sm font-medium text-text-on-accent transition-colors hover:bg-accent-hover">' . esc_html($buttonLabel) . '</button>'
            . $links
            . '</form>';

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => trim('w-full ' . $classes)]), $html);
    }

    private static function field(string $label, string $name, string $type, string $autocomplete): string
    {
        return '<label class="flex flex-col gap-2 text-sm font-medium text-text-base"><span>' . esc_html__($label, 'blocky') . '</span><input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" autocomplete="' . esc_attr($autocomplete) . '" class="min-h-11 rounded-button border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-base outline-none transition-colors focus:border-accent-base" required /></label>'; // phpcs:ignore WordPress.WP.I18n -- callers pass literal label strings.
    }

    private static function resolveFormId(Node $node, int $postId, string $prefix): string
    {
        $configured = trim((string) ($node->props['formId'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        return $postId > 0 ? $prefix . '-' . $postId . '-' . $node->id : $prefix . '-' . $node->id;
    }

    private static function resolveCurrentPostId(): int
    {
        $postId = function_exists('get_the_ID') ? (int) get_the_ID() : 0;
        if ($postId > 0) {
            return $postId;
        }

        return function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
    }

    private static function renderStatus(string $formId): string
    {
        $status = isset($_GET['blocky_login']) ? sanitize_key((string) $_GET['blocky_login']) : '';
        $statusFormId = isset($_GET['blocky_form_id']) ? sanitize_text_field((string) $_GET['blocky_form_id']) : '';
        if ($status === '' || $statusFormId !== $formId) {
            return '';
        }

        $message = match ($status) {
            'signed-in' => __('Welcome back, you are now signed in.', 'blocky'),
            'invalid' => __('Invalid username or password.', 'blocky'),
            'failed' => __('The login request could not be completed.', 'blocky'),
            default => '',
        };
        if ($message === '') {
            return '';
        }

        $classes = $status === 'signed-in'
            ? 'mb-4 rounded-base border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900'
            : 'mb-4 rounded-base border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900';

        return HtmlString::element('div', ['class' => $classes, 'role' => 'status'], esc_html($message))->toString();
    }
}