<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class BreadcrumbsRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $separator = (string) ($node->props['separator'] ?? '/');
        $showCurrent = (bool) ($node->props['showCurrent'] ?? true);
        $items = [['label' => 'Home', 'href' => \home_url('/')]];

        $postId = \get_the_ID() ?: 0;
        if ($postId > 0) {
            foreach (array_reverse(\get_post_ancestors($postId)) as $ancestorId) {
                $items[] = [
                    'label' => (string) \get_the_title($ancestorId),
                    'href' => (string) \get_permalink($ancestorId),
                ];
            }

            if ($showCurrent) {
                $items[] = [
                    'label' => (string) \get_the_title($postId),
                    'href' => '',
                ];
            }
        } elseif ($ctx->isEditorMode()) {
            $items[] = ['label' => 'Current page', 'href' => ''];
        }

        $html = '';
        foreach ($items as $index => $item) {
            if ($index > 0) {
                $html .= '<span class="text-text-faint">' . \esc_html($separator) . '</span>';
            }

            $html .= $item['href'] !== ''
                ? '<a href="' . \esc_url($item['href']) . '" class="text-text-muted hover:text-accent-text">' . \esc_html($item['label']) . '</a>'
                : '<span class="text-text-base">' . \esc_html($item['label']) . '</span>';
        }

        return HtmlString::element('nav', $ctx->blockAttrs($node, ['aria-label' => 'Breadcrumb', 'class' => 'flex flex-wrap items-center gap-2 text-sm']), $html);
    }
}