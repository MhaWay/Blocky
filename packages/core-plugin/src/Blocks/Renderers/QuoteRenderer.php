<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class QuoteRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $quote = (string) ($node->props['quote'] ?? '');
        $citation = (string) ($node->props['citation'] ?? '');

        $content = '<p class="text-xl font-medium leading-relaxed text-text-base">' . \wp_kses_post($quote) . '</p>';
        if ($citation !== '') {
            $content .= '<cite class="mt-3 block text-sm not-italic text-text-muted">' . \esc_html($citation) . '</cite>';
        }

        return HtmlString::element(
            'blockquote',
            $ctx->blockAttrs($node, ['class' => 'border-l-4 border-accent-base pl-5']),
            $content,
        );
    }
}