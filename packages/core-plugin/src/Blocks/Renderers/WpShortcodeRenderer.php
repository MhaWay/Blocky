<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class WpShortcodeRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $shortcode = (string) ($node->props['shortcode'] ?? '');

        if ($shortcode === '') {
            if ($ctx->isEditorMode()) {
                return HtmlString::element(
                    'div',
                    $ctx->blockAttrs($node, ['style' => 'border:1px dashed #d1d5db;padding:12px;border-radius:6px;']),
                    '<span style="font-size:12px;color:#9ca3af;font-style:italic">[shortcode]</span>',
                );
            }
            return HtmlString::of('');
        }

        $output = \do_shortcode($shortcode);
        return HtmlString::element('div', $ctx->blockAttrs($node, []), $output);
    }
}
