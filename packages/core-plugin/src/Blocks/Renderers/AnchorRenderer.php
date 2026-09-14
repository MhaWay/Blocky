<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class AnchorRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $anchorId = trim((string) ($node->props['anchorId'] ?? 'section-anchor'));
        $label = trim((string) ($node->props['label'] ?? $anchorId));

        if ($ctx->isEditorMode()) {
            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['id' => $anchorId, 'class' => 'inline-flex items-center gap-2 rounded-badge border border-dashed border-accent-base bg-accent-subtle px-3 py-2 text-xs font-semibold uppercase tracking-wide text-accent-text']),
                '<span aria-hidden="true">#</span><span>' . \esc_html($label === '' ? $anchorId : $label) . '</span>'
            );
        }

        return HtmlString::element('span', $ctx->blockAttrs($node, ['id' => $anchorId, 'class' => 'block relative -top-24 invisible']), '');
    }
}