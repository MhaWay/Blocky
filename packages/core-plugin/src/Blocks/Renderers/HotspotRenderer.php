<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class HotspotRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $attachmentId = (int) ($node->props['attachmentId'] ?? 0);
        $points = self::parsePoints((string) ($node->props['points'] ?? ''));
        $panelClasses = $ctx->resolveVariantClasses($node, [
            'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base'],
        ]);

        $image = $attachmentId > 0
            ? (string) \wp_get_attachment_image($attachmentId, 'large', false, ['class' => 'aspect-[4/3] w-full rounded-card object-cover'])
            : '<div class="flex aspect-[4/3] w-full items-center justify-center rounded-card border border-dashed border-border-base bg-surface-elevated text-sm text-text-muted">Select image</div>';

        $buttons = '';
        $panels = '';
        foreach ($points as $index => $point) {
            $buttons .= '<button type="button" data-bky-hotspot-button="' . $index . '" style="left:' . \esc_attr((string) $point['x']) . '%;top:' . \esc_attr((string) $point['y']) . '%;" class="absolute flex h-8 w-8 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white bg-accent-base text-sm font-semibold text-text-on-accent shadow">' . ($index + 1) . '</button>';
            $panels .= '<div data-bky-hotspot-panel="' . $index . '" class="' . ($index === 0 ? '' : 'hidden ') . 'space-y-2 rounded-card p-5 ' . \esc_attr($panelClasses) . '">'
                . '<div class="text-sm font-semibold uppercase tracking-[0.18em] opacity-70">' . \esc_html($point['label']) . '</div>'
                . '<p class="text-sm leading-6 opacity-85">' . \esc_html($point['content']) . '</p>'
                . '</div>';
        }

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['data-bky-hotspot-root' => 'bky-hotspot-' . $node->id, 'class' => 'space-y-4']),
            '<div class="relative overflow-hidden rounded-card">' . $image . $buttons . '</div>'
            . ($panels !== '' ? '<div class="space-y-3">' . $panels . '</div>' : '')
            . self::script($ctx)
        );
    }

    /** @return array<int, array{label:string, x:float, y:float, content:string}> */
    private static function parsePoints(string $raw): array
    {
        $points = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$label, $x, $y, $content] = array_pad(array_map('trim', explode('|', $line, 4)), 4, '');
            $points[] = [
                'label' => $label !== '' ? $label : 'Point',
                'x' => max(0.0, min(100.0, (float) $x)),
                'y' => max(0.0, min(100.0, (float) $y)),
                'content' => $content !== '' ? $content : 'Describe this highlighted area.',
            ];
        }
        return $points;
    }

    private static function script(RenderContext $ctx): string
    {
        if ($ctx->isEditorMode()) {
            return '';
        }

        return '<script>(function(){var root=document.currentScript.closest("[data-bky-hotspot-root]");if(!root)return;var buttons=[].slice.call(root.querySelectorAll("[data-bky-hotspot-button]"));var panels=[].slice.call(root.querySelectorAll("[data-bky-hotspot-panel]"));if(!buttons.length||!panels.length)return;function activate(index){buttons.forEach(function(button,i){button.classList.toggle("ring-4",i===index);button.classList.toggle("ring-white/60",i===index);});panels.forEach(function(panel,i){panel.classList.toggle("hidden",i!==index);});}buttons.forEach(function(button,index){button.addEventListener("click",function(){activate(index);});});activate(0);})();</script>';
    }
}