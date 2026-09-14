<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class HtmlRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $html = (string) ($node->props['html'] ?? '');

        if ($html === '' && $ctx->isEditorMode()) {
            $html = '<p>Custom HTML</p>';
        }

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['class' => 'bky-html-block']),
            \wp_kses_post($html),
        );
    }
}