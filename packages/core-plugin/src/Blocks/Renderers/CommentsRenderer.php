<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class CommentsRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $postId = $ctx->currentPostId() > 0 ? $ctx->currentPostId() : self::resolveCurrentPostId();
        if ($postId <= 0) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Comments will render for the current post.' : '');
        }

        $comments = get_comments([
            'post_id' => $postId,
            'status' => 'approve',
            'number' => max(1, (int) ($node->props['perPage'] ?? 6)),
            'order' => strtoupper((string) ($node->props['order'] ?? 'asc')),
        ]);

        if (!is_array($comments) || $comments === []) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Comments will render here once the post has discussion.' : __('No comments yet.', 'blocky'));
        }

        $title = trim((string) ($node->props['title'] ?? 'Discussion'));
        $showAvatar = (bool) ($node->props['showAvatar'] ?? true);
        $showDate = (bool) ($node->props['showDate'] ?? true);

        $items = implode('', array_map(static function ($comment) use ($showAvatar, $showDate): string {
            if (!$comment instanceof \WP_Comment) {
                return '';
            }

            return '<article class="rounded-card border border-border-subtle bg-surface-base p-4">'
                . '<div class="flex gap-4">'
                . ($showAvatar ? '<div class="h-10 w-10 overflow-hidden rounded-full shrink-0">' . get_avatar($comment, 48, '', (string) $comment->comment_author, ['class' => 'h-full w-full object-cover']) . '</div>' : '')
                . '<div class="min-w-0 flex-1">'
                . '<div class="flex flex-wrap items-center gap-2"><span class="text-sm font-semibold text-text-base">' . esc_html((string) $comment->comment_author) . '</span>'
                . ($showDate ? '<time class="text-xs text-text-muted">' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime((string) $comment->comment_date_gmt . ' GMT'))) . '</time>' : '')
                . '</div>'
                . '<div class="mt-2 text-sm leading-6 text-text-muted">' . wp_kses_post(wpautop((string) $comment->comment_content)) . '</div>'
                . '</div></div></article>';
        }, $comments));

        $html = ($title !== '' ? '<h3 class="mb-4 text-lg font-semibold text-text-base">' . esc_html($title) . '</h3>' : '') . '<div class="space-y-4">' . $items . '</div>';
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