<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class CommentFormRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $postId = $ctx->currentPostId() > 0 ? $ctx->currentPostId() : self::resolveCurrentPostId();
        if ($postId <= 0) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Comment form will render for the current post.' : '');
        }

        if ($ctx->isEditorMode()) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'rounded-card border border-dashed border-border-base bg-surface-elevated p-4 text-sm text-text-muted']), 'Comment form preview will render on the public page.');
        }

        if (!comments_open($postId)) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), esc_html__('Comments are closed.', 'blocky'));
        }

        ob_start();
        comment_form([
            'title_reply' => (string) ($node->props['titleReply'] ?? 'Leave a reply'),
            'label_submit' => (string) ($node->props['buttonLabel'] ?? 'Post comment'),
            'comment_notes_before' => (bool) ($node->props['showNotes'] ?? true) ? null : '',
            'class_submit' => 'submit inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-3 text-sm font-medium text-text-on-accent transition-colors hover:bg-accent-hover',
        ], $postId);
        $html = (string) ob_get_clean();

        return HtmlString::element('section', $ctx->blockAttrs($node), $html);
    }

    private static function resolveCurrentPostId(): int
    {
        $postId = function_exists('get_the_ID') ? (int) get_the_ID() : 0;
        if ($postId > 0) {
            return $postId;
        }

        return function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
    }
}