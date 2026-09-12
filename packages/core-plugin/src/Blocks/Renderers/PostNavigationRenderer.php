<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class PostNavigationRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $postId = $ctx->currentPostId() > 0 ? $ctx->currentPostId() : self::resolveCurrentPostId();
        if ($postId <= 0) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Previous and next post links will render here.' : '');
        }

        $previous = get_previous_post();
        $next = get_next_post();
        if (!$previous instanceof \WP_Post && !$next instanceof \WP_Post) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Post navigation will render for neighboring posts.' : '');
        }

        $showLabels = (bool) ($node->props['showLabels'] ?? true);
        $layoutClass = $ctx->resolveVariantClasses($node, ['layout' => ['between' => 'flex-row justify-between items-start', 'stacked' => 'flex-col gap-4 items-start']]);
        $prevLabel = (string) ($node->props['prevLabel'] ?? 'Previous post');
        $nextLabel = (string) ($node->props['nextLabel'] ?? 'Next post');

        $html = '<nav class="flex flex-wrap gap-4 ' . esc_attr($layoutClass) . '" aria-label="Post navigation">'
            . $this->linkHtml($previous, $prevLabel, $showLabels, '←')
            . $this->linkHtml($next, $nextLabel, $showLabels, '→', true)
            . '</nav>';

        return HtmlString::element('div', $ctx->blockAttrs($node), $html);
    }

    private function linkHtml(?\WP_Post $post, string $label, bool $showLabel, string $arrow, bool $alignEnd = false): string
    {
        if (!$post instanceof \WP_Post) {
            return '';
        }

        return '<a href="' . esc_url((string) get_permalink($post)) . '" class="min-w-0 rounded-card border border-border-subtle bg-surface-base px-4 py-3 ' . ($alignEnd ? 'text-right' : '') . '">'
            . ($showLabel ? '<span class="block text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">' . esc_html($label) . '</span>' : '')
            . '<span class="mt-1 inline-flex items-center gap-2 text-sm font-medium text-text-base">' . ($alignEnd ? '' : '<span aria-hidden="true">' . esc_html($arrow) . '</span>') . '<span>' . esc_html((string) get_the_title($post)) . '</span>' . ($alignEnd ? '<span aria-hidden="true">' . esc_html($arrow) . '</span>' : '') . '</span>'
            . '</a>';
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