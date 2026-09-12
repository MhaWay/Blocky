<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class ImageCarouselRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $items = self::parseItems((string) ($node->props['items'] ?? ''));
        if ($items === []) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Add image IDs or URLs to build the carousel.' : '');
        }

        $rootId = 'bky-image-carousel-' . $node->id;
        $slideClasses = $ctx->resolveVariantClasses($node, [
            'slidesVisible' => [
                '1' => 'basis-full',
                '2' => 'basis-full md:basis-1/2',
                '3' => 'basis-full md:basis-1/2 lg:basis-1/3',
            ],
        ]);
        $trackClasses = $ctx->resolveVariantClasses($node, [
            'gap' => [
                'sm' => 'gap-3',
                'base' => 'gap-4',
                'lg' => 'gap-6',
            ],
        ]);
        $mediaClasses = $ctx->resolveVariantClasses($node, [
            'aspectRatio' => [
                'square' => 'aspect-square',
                'video' => 'aspect-video',
                'wide' => 'aspect-[21/9]',
            ],
        ]);
        $showCaptions = (bool) ($node->props['showCaptions'] ?? true);
        $autoPlay = !$ctx->isEditorMode() && (bool) ($node->props['autoPlay'] ?? false) && count($items) > 1;
        $interval = max(2000, min(15000, ((int) ($node->props['interval'] ?? 5)) * 1000));

        $slides = '';
        $dots = '';
        foreach ($items as $index => $item) {
            $caption = $showCaptions && $item['caption'] !== ''
                ? '<figcaption class="border-t border-border-subtle px-4 py-3 text-sm text-text-base">' . \esc_html($item['caption']) . '</figcaption>'
                : '';
            $slides .= '<figure data-bky-carousel-slide="' . $index . '" class="' . \esc_attr(trim('shrink-0 snap-start overflow-hidden rounded-image border border-border-subtle bg-surface-elevated ' . $slideClasses)) . '">'
                . '<div class="' . \esc_attr(trim('overflow-hidden bg-surface-elevated ' . $mediaClasses)) . '">' . self::renderImage($item['source']) . '</div>'
                . $caption
                . '</figure>';
            $dots .= '<button type="button" data-bky-carousel-dot="' . $index . '" aria-label="Go to slide ' . ($index + 1) . '" class="h-2.5 w-2.5 rounded-full bg-border-strong/40 transition-colors"></button>';
        }

        $controls = count($items) > 1
            ? '<div class="flex items-center justify-between gap-3">'
                . '<div class="flex gap-2">'
                . '<button type="button" data-bky-carousel-prev class="inline-flex items-center justify-center rounded-button border border-border-subtle bg-surface-base px-3 py-2 text-sm font-medium text-text-base">Previous</button>'
                . '<button type="button" data-bky-carousel-next class="inline-flex items-center justify-center rounded-button border border-border-subtle bg-surface-base px-3 py-2 text-sm font-medium text-text-base">Next</button>'
                . '</div>'
                . '<div class="flex items-center gap-2">' . $dots . '</div>'
                . '</div>'
            : '';

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['data-bky-carousel-root' => $rootId, 'class' => 'space-y-4']),
            '<div data-bky-carousel-track class="' . \esc_attr(trim('flex overflow-x-auto snap-x snap-mandatory scroll-smooth ' . $trackClasses)) . '">' . $slides . '</div>'
            . $controls
            . self::script($ctx, $autoPlay, $interval)
        );
    }

    /** @return array<int, array{source:string, caption:string}> */
    private static function parseItems(string $raw): array
    {
        $items = [];
        foreach (preg_split('/\s*,\s*|\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$source, $caption] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($source === '') {
                continue;
            }

            $items[] = ['source' => $source, 'caption' => $caption];
        }

        return $items;
    }

    private static function renderImage(string $item): string
    {
        if (ctype_digit($item)) {
            return (string) \wp_get_attachment_image((int) $item, 'large', false, ['class' => 'h-full w-full object-cover']);
        }

        return '<img src="' . \esc_url($item) . '" alt="" class="h-full w-full object-cover" loading="lazy" />';
    }

    private static function script(RenderContext $ctx, bool $autoPlay, int $interval): string
    {
        if ($ctx->isEditorMode()) {
            return '';
        }

        return '<script>(function(){var root=document.currentScript.closest("[data-bky-carousel-root]");if(!root)return;var track=root.querySelector("[data-bky-carousel-track]");if(!track)return;var slides=[].slice.call(root.querySelectorAll("[data-bky-carousel-slide]"));var dots=[].slice.call(root.querySelectorAll("[data-bky-carousel-dot]"));if(!slides.length)return;var index=0;var timer=null;function sync(){dots.forEach(function(dot,i){dot.className="h-2.5 w-2.5 rounded-full transition-colors "+(i===index?"bg-accent-base":"bg-border-strong/40");});}function go(next){index=(next+slides.length)%slides.length;slides[index].scrollIntoView({behavior:"smooth",inline:"start",block:"nearest"});sync();}function restart(){if(!' . ($autoPlay ? 'true' : 'false') . ')return;if(timer)window.clearInterval(timer);timer=window.setInterval(function(){go(index+1);},' . $interval . ');}var prev=root.querySelector("[data-bky-carousel-prev]");var next=root.querySelector("[data-bky-carousel-next]");if(prev)prev.addEventListener("click",function(){go(index-1);restart();});if(next)next.addEventListener("click",function(){go(index+1);restart();});dots.forEach(function(dot,i){dot.addEventListener("click",function(){go(i);restart();});});sync();restart();})();</script>';
    }
}