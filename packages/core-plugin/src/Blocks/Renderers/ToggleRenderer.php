<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class ToggleRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $title = (string) ($node->props['title'] ?? 'Toggle title');
        $content = (string) ($node->props['content'] ?? 'Toggle content');
        $open = (bool) ($node->props['open'] ?? false);

        return HtmlString::element('details', $ctx->blockAttrs($node, ['class' => 'rounded-card border border-border-subtle bg-surface-base p-4']) + ($open ? ['open' => true] : []), '<summary class="cursor-pointer list-none text-sm font-semibold">' . \esc_html($title) . '</summary><div class="pt-3 text-sm leading-6 text-text-muted">' . \esc_html($content) . '</div>');
    }
}