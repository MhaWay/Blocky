<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class ContentCarouselRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $items = self::parseItems((string) ($node->props['items'] ?? "Slide 1|Highlight your offer|Learn more|#\nSlide 2|Guide visitors to the next step|Contact us|#"));
        if ($items === []) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Add slide content to build the carousel.' : '');
        }

        $rootId = 'bky-content-carousel-' . $node->id;
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
        $cardClasses = $ctx->resolveVariantClasses($node, [
            'align' => [
                'start' => 'items-start text-left',
                'center' => 'items-center text-center',
            ],
            'tone' => [
                'surface' => 'border border-border-subtle bg-surface-base text-text-base',
                'accent' => 'bg-accent-subtle text-text-base',
                'contrast' => 'bg-text-base text-text-on-accent',
            ],
        ]);
        $autoPlay = !$ctx->isEditorMode() && (bool) ($node->props['autoPlay'] ?? false) && count($items) > 1;
        $interval = max(2000, min(15000, ((int) ($node->props['interval'] ?? 5)) * 1000));

        $slides = '';
        $dots = '';
        foreach ($items as $index => $item) {
            $button = '';
            if ($item['button'] !== '') {
                if ($item['url'] !== '') {
                    $button = '<a href="' . \esc_url($item['url']) . '" class="inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-2 text-sm font-medium text-text-on-accent">' . \esc_html($item['button']) . '</a>';
                } else {
                    $button = '<span class="inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-2 text-sm font-medium text-text-on-accent">' . \esc_html($item['button']) . '</span>';
                }
            }

            $slides .= '<article data-bky-carousel-slide="' . $index . '" class="' . \esc_attr(trim('shrink-0 snap-start ' . $slideClasses)) . '">'
                . '<div class="' . \esc_attr(trim('flex h-full min-h-64 flex-col gap-4 rounded-card p-6 ' . $cardClasses)) . '">'
                . '<h3 class="text-xl font-semibold leading-tight">' . \esc_html($item['title']) . '</h3>'
                . '<p class="text-sm leading-6 opacity-90">' . \esc_html($item['content']) . '</p>'
                . ($button !== '' ? '<div class="pt-2">' . $button . '</div>' : '')
                . '</div>'
                . '</article>';
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
            $ctx->blockAttrs($node, ['data-bky-carousel-root' => $rootId,
                'data-bky-carousel-autoplay' => $autoPlay ? 'true' : 'false',
                'data-bky-carousel-interval' => (string) $interval, 'class' => 'space-y-4']),
            '<div data-bky-carousel-track class="' . \esc_attr(trim('flex overflow-x-auto snap-x snap-mandatory scroll-smooth ' . $trackClasses)) . '">' . $slides . '</div>'
            . $controls
            . self::script($ctx, $autoPlay, $interval)
        );
    }

    /** @return array<int, array{title:string, content:string, button:string, url:string}> */
    private static function parseItems(string $raw): array
    {
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$title, $content, $button, $url] = array_pad(array_map('trim', explode('|', $line, 4)), 4, '');
            $items[] = [
                'title' => $title !== '' ? $title : 'Slide',
                'content' => $content !== '' ? $content : 'Add slide content.',
                'button' => $button,
                'url' => $url,
            ];
        }

        return $items;
    }

    private static function script(RenderContext $ctx, bool $autoPlay, int $interval): string
    {
        return '';
    }
}