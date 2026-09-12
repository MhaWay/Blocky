<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Node;
use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class WpTemplatePartRenderer implements BlockRendererInterface
{
    /** @var array<int, bool> */
    private static array $renderStack = [];

    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $templatePostId = \absint($node->props['postId'] ?? 0);
        $currentPostId = (int) (\get_the_ID() ?: 0);

        if ($templatePostId <= 0) {
            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['class' => 'blocky-template-part']),
                $ctx->isEditorMode()
                    ? '<p style="opacity:.55;font-style:italic">Select a Blocky page to render this template part.</p>'
                    : ''
            );
        }

        if ($currentPostId > 0 && $templatePostId === $currentPostId) {
            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['class' => 'blocky-template-part']),
                $ctx->isEditorMode()
                    ? '<p style="opacity:.55;font-style:italic">This page cannot include itself as a template part.</p>'
                    : ''
            );
        }

        if (isset(self::$renderStack[$templatePostId])) {
            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['class' => 'blocky-template-part']),
                $ctx->isEditorMode()
                    ? '<p style="opacity:.55;font-style:italic">Recursive template reference prevented.</p>'
                    : ''
            );
        }

        $document = \get_post_meta($templatePostId, '_blocky_document', true);
        if (!is_string($document) || $document === '') {
            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['class' => 'blocky-template-part']),
                $ctx->isEditorMode()
                    ? '<p style="opacity:.55;font-style:italic">The selected page does not contain a Blocky document.</p>'
                    : ''
            );
        }

        self::$renderStack[$templatePostId] = true;

        try {
            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['class' => 'blocky-template-part']),
                $ctx->renderDocument($document, false)->toString()
            );
        } finally {
            unset(self::$renderStack[$templatePostId]);
        }
    }
}