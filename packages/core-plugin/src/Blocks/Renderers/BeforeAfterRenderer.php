<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class BeforeAfterRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $beforeId = (int) ($node->props['beforeAttachmentId'] ?? 0);
        $afterId = (int) ($node->props['afterAttachmentId'] ?? 0);
        $startingPoint = max(10, min(90, (int) ($node->props['startingPoint'] ?? 50)));
        $ratioClasses = $ctx->resolveVariantClasses($node, [
            'aspectRatio' => ['square' => 'aspect-square', 'video' => 'aspect-video', 'wide' => 'aspect-[21/9]'],
        ]);

        $before = $beforeId > 0
            ? (string) \wp_get_attachment_image($beforeId, 'large', false, ['class' => 'h-full w-full object-cover'])
            : '<div class="flex h-full w-full items-center justify-center bg-surface-elevated text-sm text-text-muted">Select before image</div>';
        $after = $afterId > 0
            ? (string) \wp_get_attachment_image($afterId, 'large', false, ['class' => 'h-full w-full object-cover'])
            : '<div class="flex h-full w-full items-center justify-center bg-surface-elevated text-sm text-text-muted">Select after image</div>';

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['data-bky-before-after-root' => 'bky-before-after-' . $node->id, 'style' => '--bky-before-after:' . $startingPoint . '%;', 'class' => 'space-y-4']),
            '<div class="relative overflow-hidden rounded-card border border-border-subtle ' . \esc_attr($ratioClasses) . '">'
            . '<div class="absolute inset-0">' . $before . '</div>'
            . '<div data-bky-before-after-overlay class="absolute inset-y-0 left-0 overflow-hidden" style="width:var(--bky-before-after);">' . $after . '</div>'
            . '<div class="absolute inset-y-0 left-0 w-0.5 -translate-x-1/2 bg-white shadow" style="left:var(--bky-before-after);"></div>'
            . '<div class="absolute left-0 top-0 rounded-br-card bg-black/55 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white">Before</div>'
            . '<div class="absolute right-0 top-0 rounded-bl-card bg-black/55 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white">After</div>'
            . '</div>'
            . '<input data-bky-before-after-range type="range" min="10" max="90" value="' . $startingPoint . '" class="w-full accent-[var(--color-accent-base)]" />'
            . self::script($ctx)
        );
    }

    private static function script(RenderContext $ctx): string
    {
        if ($ctx->isEditorMode()) {
            return '';
        }

        return '<script>(function(){var root=document.currentScript.closest("[data-bky-before-after-root]");if(!root)return;var range=root.querySelector("[data-bky-before-after-range]");if(!range)return;var update=function(){root.style.setProperty("--bky-before-after",range.value+"%");};range.addEventListener("input",update);update();})();</script>';
    }
}