<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class FormRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $method = strtolower((string) ($node->props['method'] ?? 'post'));
        if (!in_array($method, ['get', 'post'], true)) {
            $method = 'post';
        }

        $action = trim((string) ($node->props['action'] ?? ''));
        $postId = $ctx->currentPostId() > 0 ? $ctx->currentPostId() : self::resolveCurrentPostId();
        $formId = self::resolveFormId($node, $postId);
        $name = trim((string) ($node->props['name'] ?? ''));
        $successMessage = trim((string) ($node->props['successMessage'] ?? ''));
        $noValidate = (bool) ($node->props['noValidate'] ?? false);
        $enctype = (string) ($node->props['enctype'] ?? 'default');
        $usesBuiltInSubmission = !$ctx->isEditorMode() && $action === '' && $method === 'post';

        $formClasses = $ctx->resolveVariantClasses($node, [
            'padding' => [
                'none' => 'p-0',
                'sm' => 'p-4',
                'base' => 'p-6',
                'lg' => 'p-8',
            ],
            'background' => [
                'transparent' => '',
                'surface' => 'bg-surface-base',
                'elevated' => 'bg-surface-elevated',
                'accent' => 'bg-accent-subtle',
            ],
            'border' => [
                'none' => '',
                'subtle' => 'border border-border-subtle',
                'base' => 'border border-border-base',
                'strong' => 'border border-border-strong',
            ],
            'radius' => [
                'none' => 'rounded-none',
                'base' => 'rounded-base',
                'lg' => 'rounded-lg',
                'xl' => 'rounded-xl',
            ],
            'shadow' => [
                'none' => 'shadow-none',
                'sm' => 'shadow-sm',
                'base' => 'shadow',
                'md' => 'shadow-md',
                'lg' => 'shadow-lg',
            ],
        ]);

        $slotLayoutClasses = $ctx->resolveVariantClasses($node, [
            'gap' => [
                'sm' => 'gap-3',
                'base' => 'gap-4',
                'lg' => 'gap-6',
            ],
            'align' => [
                'start' => 'justify-start',
                'center' => 'justify-center',
                'end' => 'justify-end',
            ],
        ]);

        $slotAttrs = [
            'class' => trim('bky-form-slot flex flex-wrap items-start ' . $slotLayoutClasses),
        ];
        if ($ctx->isEditorMode()) {
            $slotAttrs['data-bky-slot-label'] = 'Drop form fields here';
        }

        $statusHtml = $ctx->isEditorMode()
            ? ''
            : self::renderStatusMessage($formId, $successMessage);

        $submissionFields = $usesBuiltInSubmission
            ? self::submissionFields($formId, $postId)
            : '';

        $inner = $statusHtml
            . $submissionFields
            . '<div' . HtmlString::attrs($ctx->slotAttrs($node, 'default', $slotAttrs)) . '>'
            . $ctx->renderSlot($node)->toString()
            . '</div>';

        return HtmlString::element('form', $ctx->containerAttrs($node, [
            'method' => $method,
            'action' => $usesBuiltInSubmission ? \esc_url(\admin_url('admin-post.php')) : ($action !== '' ? \esc_url($action) : null),
            'id' => 'bky-form-' . \sanitize_html_class($formId),
            'data-bky-form-id' => $formId,
            'name' => $name !== '' ? $name : null,
            'enctype' => $enctype === 'multipart' ? 'multipart/form-data' : null,
            'novalidate' => $noValidate,
            'class' => trim('w-full ' . $formClasses),
        ]), $inner);
    }

    private static function resolveFormId(Node $node, int $postId = 0): string
    {
        $configured = trim((string) ($node->props['formId'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        if ($postId > 0) {
            return 'form-' . $postId . '-' . $node->id;
        }

        return 'form-' . $node->id;
    }

    private static function submissionFields(string $formId, int $postId): string
    {
        $fields = [];
        $fields[] = HtmlString::void('input', [
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'blocky_frontend_form_submit',
        ])->toString();
        $fields[] = HtmlString::void('input', [
            'type' => 'hidden',
            'name' => '_blocky_form_submission',
            'value' => '1',
        ])->toString();
        $fields[] = HtmlString::void('input', [
            'type' => 'hidden',
            'name' => '_blocky_form_id',
            'value' => $formId,
        ])->toString();
        $fields[] = HtmlString::void('input', [
            'type' => 'hidden',
            'name' => '_blocky_form_post_id',
            'value' => $postId > 0 ? (string) $postId : '',
        ])->toString();
        $fields[] = \wp_nonce_field('blocky_frontend_form_' . $formId, '_blocky_form_nonce', false, false);

        return implode('', $fields);
    }

    private static function resolveCurrentPostId(): int
    {
        $postId = \function_exists('get_the_ID') ? (int) \get_the_ID() : 0;
        if ($postId > 0) {
            return $postId;
        }

        $postId = \function_exists('get_queried_object_id') ? (int) \get_queried_object_id() : 0;
        if ($postId > 0) {
            return $postId;
        }

        return 0;
    }

    private static function renderStatusMessage(string $formId, string $configuredSuccessMessage): string
    {
        $status = isset($_GET['blocky_form']) ? \sanitize_key((string) $_GET['blocky_form']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only render flag for post-submit status; no state change.
        $statusFormId = isset($_GET['blocky_form_id']) ? \sanitize_text_field((string) \wp_unslash($_GET['blocky_form_id'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only render flag for post-submit status; no state change.

        if ($status === '' || $statusFormId !== $formId) {
            return '';
        }

        $message = match ($status) {
            'submitted' => $configuredSuccessMessage !== '' ? $configuredSuccessMessage : __('Thanks, your submission has been saved.', 'blocky'),
            'invalid' => __('The form session expired. Please try again.', 'blocky'),
            'failed' => __('The form could not be saved. Please try again.', 'blocky'),
            default => '',
        };

        if ($message === '') {
            return '';
        }

        $classes = $status === 'submitted'
            ? 'mb-4 rounded-base border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900'
            : 'mb-4 rounded-base border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900';

        return HtmlString::element('div', [
            'class' => $classes,
            'role' => 'status',
        ], \esc_html($message))->toString();
    }
}