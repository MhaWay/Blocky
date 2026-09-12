<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class PaginationRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $alignClass = $ctx->resolveVariantClasses($node, [
            'align' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'],
        ]);

        if ($ctx->isEditorMode()) {
            $preview = '<span class="rounded-button border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-muted">1</span>'
                . '<span class="rounded-button bg-accent-base px-3 py-2 text-sm text-text-on-accent">2</span>'
                . '<span class="rounded-button border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-muted">3</span>';
            return HtmlString::element('nav', $ctx->blockAttrs($node, ['class' => trim('flex gap-2 ' . $alignClass)]), $preview);
        }

        $links = \paginate_links([
            'type' => 'array',
            'prev_text' => (string) ($node->props['prevLabel'] ?? 'Previous'),
            'next_text' => (string) ($node->props['nextLabel'] ?? 'Next'),
        ]);

        if (!is_array($links) || $links === []) {
            return HtmlString::element('nav', $ctx->blockAttrs($node, ['class' => trim('flex gap-2 ' . $alignClass)]), '');
        }

        $items = implode('', array_map(
            static fn(string $link): string => '<span class="pagination-item">' . $link . '</span>',
            $links,
        ));

        return HtmlString::element('nav', $ctx->blockAttrs($node, ['class' => trim('flex flex-wrap gap-2 ' . $alignClass)]), $items);
    }
}