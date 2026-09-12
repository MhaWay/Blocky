<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class SocialIconsRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $items = self::parseItems((string) ($node->props['items'] ?? 'facebook|https://facebook.com'));
        $wrapperClasses = $ctx->resolveVariantClasses($node, [
            'layout' => [
                'row' => 'flex-row flex-wrap',
                'column' => 'flex-col',
            ],
            'gap' => [
                'sm' => 'gap-2',
                'base' => 'gap-3',
                'lg' => 'gap-4',
            ],
            'align' => [
                'start' => 'items-start justify-start',
                'center' => 'items-center justify-center',
                'end' => 'items-end justify-end',
            ],
        ]);

        $itemClasses = $ctx->resolveVariantClasses($node, [
            'size' => [
                'sm' => 'h-9 min-w-9 px-3 text-sm',
                'base' => 'h-11 min-w-11 px-4 text-base',
                'lg' => 'h-12 min-w-12 px-5 text-lg',
            ],
            'tone' => [
                'brand' => 'bg-accent-base text-text-on-accent',
                'neutral' => 'bg-surface-elevated text-text-base border border-border-base',
                'ghost' => 'bg-transparent text-text-base',
            ],
            'radius' => [
                'none' => 'rounded-none',
                'base' => 'rounded-base',
                'button' => 'rounded-button',
                'full' => 'rounded-full',
            ],
        ]);

        $links = implode('', array_map(
            static function (array $item) use ($ctx, $node, $itemClasses): string {
                $label = $item['label'];
                $symbol = $item['symbol'];
                $url = $item['url'];
                return HtmlString::element('a', $ctx->containerAttrs($node, [
                    'href' => \esc_url($url === '' ? '#' : $url),
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                    'aria-label' => $label,
                    'class' => trim('inline-flex items-center justify-center gap-2 font-medium transition-colors ' . $itemClasses),
                ]), '<span aria-hidden="true">' . \esc_html($symbol) . '</span><span>' . \esc_html($label) . '</span>')->toString();
            },
            $items,
        ));

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => trim('flex ' . $wrapperClasses)]), $links);
    }

    /**
     * @return array<int, array{label:string, symbol:string, url:string}>
     */
    private static function parseItems(string $raw): array
    {
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$network, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $label = $network !== '' ? ucfirst($network) : 'Social';
            $items[] = [
                'label' => $label,
                'symbol' => self::symbolFor($network),
                'url' => $url,
            ];
        }

        return $items !== [] ? $items : [[
            'label' => 'Facebook',
            'symbol' => 'f',
            'url' => 'https://facebook.com',
        ]];
    }

    private static function symbolFor(string $network): string
    {
        return match (strtolower($network)) {
            'facebook' => 'f',
            'instagram' => '◎',
            'linkedin' => 'in',
            'github' => '⌘',
            'youtube' => '▶',
            'x', 'twitter' => '𝕏',
            'tiktok' => '♪',
            default => '•',
        };
    }
}