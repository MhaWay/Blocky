<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class RegisterFormRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $postId = $ctx->currentPostId() > 0 ? $ctx->currentPostId() : self::resolveCurrentPostId();
        $formId = self::resolveFormId($node, $postId);
        $redirectUrl = trim((string) ($node->props['redirectUrl'] ?? ''));
        $title = trim((string) ($node->props['title'] ?? 'Create your account'));
        $submitLabel = trim((string) ($node->props['submitLabel'] ?? 'Create account'));
        $successMessage = trim((string) ($node->props['successMessage'] ?? 'Your account has been created.'));
        $loginAfterRegister = (bool) ($node->props['loginAfterRegister'] ?? false);
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

        $disabledNotice = !$ctx->isEditorMode() && !get_option('users_can_register')
            ? HtmlString::element('div', ['class' => 'mb-4 rounded-base border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900'], esc_html__('Registration is currently disabled on this site.', 'blocky'))->toString()
            : '';

        $html = $disabledNotice
            . ($ctx->isEditorMode() ? '' : self::renderStatus($formId, $successMessage))
            . ($title !== '' ? '<h3 class="text-lg font-semibold text-text-base">' . esc_html($title) . '</h3>' : '')
            . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="mt-4 flex flex-col ' . esc_attr($gapClass) . '">'
            . HtmlString::void('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'blocky_frontend_register_submit'])->toString()
            . HtmlString::void('input', ['type' => 'hidden', 'name' => '_blocky_register_submission', 'value' => '1'])->toString()
            . HtmlString::void('input', ['type' => 'hidden', 'name' => '_blocky_form_id', 'value' => $formId])->toString()
            . HtmlString::void('input', ['type' => 'hidden', 'name' => '_blocky_form_post_id', 'value' => $postId > 0 ? (string) $postId : ''])->toString()
            . HtmlString::void('input', ['type' => 'hidden', 'name' => '_blocky_register_redirect', 'value' => $redirectUrl])->toString()
            . HtmlString::void('input', ['type' => 'hidden', 'name' => '_blocky_register_autologin', 'value' => $loginAfterRegister ? '1' : '0'])->toString()
            . wp_nonce_field('blocky_frontend_register_' . $formId, '_blocky_register_nonce', false, false)
            . self::field('Username', 'user_login', 'text', 'username')
            . self::field('Email', 'user_email', 'email', 'email')
            . self::field('Password', 'user_password', 'password', 'new-password')
            . '<button type="submit" class="inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-3 text-sm font-medium text-text-on-accent transition-colors hover:bg-accent-hover">' . esc_html($submitLabel) . '</button>'
            . '</form>';

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => trim('w-full ' . $classes)]), $html);
    }

    private static function field(string $label, string $name, string $type, string $autocomplete): string
    {
        return '<label class="flex flex-col gap-2 text-sm font-medium text-text-base"><span>' . esc_html__($label, 'blocky') . '</span><input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" autocomplete="' . esc_attr($autocomplete) . '" class="min-h-11 rounded-button border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-base outline-none transition-colors focus:border-accent-base" required /></label>';
    }

    private static function resolveFormId(Node $node, int $postId): string
    {
        $configured = trim((string) ($node->props['formId'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        return $postId > 0 ? 'register-' . $postId . '-' . $node->id : 'register-' . $node->id;
    }

    private static function resolveCurrentPostId(): int
    {
        $postId = function_exists('get_the_ID') ? (int) get_the_ID() : 0;
        if ($postId > 0) {
            return $postId;
        }

        return function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
    }

    private static function renderStatus(string $formId, string $successMessage): string
    {
        $status = isset($_GET['blocky_register']) ? sanitize_key((string) $_GET['blocky_register']) : '';
        $statusFormId = isset($_GET['blocky_form_id']) ? sanitize_text_field((string) $_GET['blocky_form_id']) : '';
        if ($status === '' || $statusFormId !== $formId) {
            return '';
        }

        $message = match ($status) {
            'registered' => $successMessage !== '' ? $successMessage : __('Your account has been created.', 'blocky'),
            'exists' => __('That username or email is already in use.', 'blocky'),
            'invalid' => __('Please complete all required fields with valid values.', 'blocky'),
            'disabled' => __('Registration is currently disabled on this site.', 'blocky'),
            'failed' => __('The account could not be created. Please try again.', 'blocky'),
            default => '',
        };
        if ($message === '') {
            return '';
        }

        $classes = $status === 'registered'
            ? 'mb-4 rounded-base border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900'
            : 'mb-4 rounded-base border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900';

        return HtmlString::element('div', ['class' => $classes, 'role' => 'status'], esc_html($message))->toString();
    }
}