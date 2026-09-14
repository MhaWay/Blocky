<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class TextRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $content    = (string) ($node->props['content'] ?? '');
        $varClasses = $ctx->resolveVariantClasses($node, [
            'size'  => ['sm' => 'text-sm', 'base' => 'text-base', 'lg' => 'text-lg', 'xl' => 'text-xl'],
            'color' => ['base' => 'text-text-base', 'muted' => 'text-text-muted', 'faint' => 'text-text-faint'],
            'align' => ['start' => 'text-start', 'center' => 'text-center', 'end' => 'text-end'],
        ]);

        return HtmlString::element(
            'p',
            $ctx->blockAttrs($node, ['class' => trim("leading-relaxed {$varClasses}")]),
            wp_kses_post($content),
        );
    }
}
