<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class ContactFormRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $postId = $ctx->currentPostId() > 0 ? $ctx->currentPostId() : self::resolveCurrentPostId();
        $formId = self::resolveFormId($node, $postId);
        $title = trim((string) ($node->props['title'] ?? 'Let\'s talk'));
        $successMessage = trim((string) ($node->props['successMessage'] ?? 'Thanks, we received your message.'));
        $classes = $ctx->resolveVariantClasses($node, [
            'gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6'],
            'padding' => ['none' => 'p-0', 'sm' => 'p-4', 'base' => 'p-6', 'lg' => 'p-8'],
            'background' => ['transparent' => '', 'surface' => 'bg-surface-base', 'elevated' => 'bg-surface-elevated'],
            'border' => ['none' => '', 'subtle' => 'border border-border-subtle', 'base' => 'border border-border-base'],
            'radius' => ['none' => 'rounded-none', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'xl' => 'rounded-xl'],
        ]);
        $gapClass = $ctx->resolveVariantClasses($node, ['gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6']]);

        $html = ($ctx->isEditorMode() ? '' : self::renderStatus($formId, $successMessage))
            . ($title !== '' ? '<h3 class="text-lg font-semibold text-text-base">' . esc_html($title) . '</h3>' : '')
            . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="mt-4 flex flex-col ' . esc_attr($gapClass) . '">'
            . HtmlString::void('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'blocky_frontend_form_submit'])->toString()
            . HtmlString::void('input', ['type' => 'hidden', 'name' => '_blocky_form_submission', 'value' => '1'])->toString()
            . HtmlString::void('input', ['type' => 'hidden', 'name' => '_blocky_form_id', 'value' => $formId])->toString()
            . HtmlString::void('input', ['type' => 'hidden', 'name' => '_blocky_form_post_id', 'value' => $postId > 0 ? (string) $postId : ''])->toString()
            . wp_nonce_field('blocky_frontend_form_' . $formId, '_blocky_form_nonce', false, false)
            . self::field((string) ($node->props['nameLabel'] ?? 'Name'), 'name', 'text', 'name')
            . self::field((string) ($node->props['emailLabel'] ?? 'Email'), 'email', 'email', 'email')
            . self::field((string) ($node->props['subjectLabel'] ?? 'Subject'), 'subject', 'text', 'off')
            . self::textarea((string) ($node->props['messageLabel'] ?? 'Message'), 'message')
            . '<button type="submit" class="inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-3 text-sm font-medium text-text-on-accent transition-colors hover:bg-accent-hover">' . esc_html((string) ($node->props['submitLabel'] ?? 'Send message')) . '</button>'
            . '</form>';

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => trim('w-full ' . $classes)]), $html);
    }

    private static function field(string $label, string $name, string $type, string $autocomplete): string
    {
        return '<label class="flex flex-col gap-2 text-sm font-medium text-text-base"><span>' . esc_html($label) . '</span><input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" autocomplete="' . esc_attr($autocomplete) . '" class="min-h-11 rounded-button border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-base outline-none transition-colors focus:border-accent-base" required /></label>';
    }

    private static function textarea(string $label, string $name): string
    {
        return '<label class="flex flex-col gap-2 text-sm font-medium text-text-base"><span>' . esc_html($label) . '</span><textarea name="' . esc_attr($name) . '" rows="5" class="rounded-button border border-border-base bg-surface-elevated px-3 py-3 text-sm text-text-base outline-none transition-colors focus:border-accent-base" required></textarea></label>';
    }

    private static function resolveFormId(Node $node, int $postId): string
    {
        $configured = trim((string) ($node->props['formId'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        return $postId > 0 ? 'contact-' . $postId . '-' . $node->id : 'contact-' . $node->id;
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
        $status = isset($_GET['blocky_form']) ? sanitize_key((string) $_GET['blocky_form']) : '';
        $statusFormId = isset($_GET['blocky_form_id']) ? sanitize_text_field((string) $_GET['blocky_form_id']) : '';
        if ($status === '' || $statusFormId !== $formId) {
            return '';
        }

        $message = match ($status) {
            'submitted' => $successMessage !== '' ? $successMessage : __('Thanks, we received your message.', 'blocky'),
            'invalid' => __('The form session expired. Please try again.', 'blocky'),
            'failed' => __('The message could not be sent. Please try again.', 'blocky'),
            default => '',
        };
        if ($message === '') {
            return '';
        }

        $classes = $status === 'submitted'
            ? 'mb-4 rounded-base border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900'
            : 'mb-4 rounded-base border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900';

        return HtmlString::element('div', ['class' => $classes, 'role' => 'status'], esc_html($message))->toString();
    }
}