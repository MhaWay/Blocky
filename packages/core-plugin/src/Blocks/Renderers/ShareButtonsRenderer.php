<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class ShareButtonsRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $networks = self::parseNetworks((string) ($node->props['networks'] ?? 'facebook,x,linkedin'));
        $url = \get_permalink() ?: \home_url('/');
        $title = \get_the_title() ?: (string) ($node->props['title'] ?? 'Share');

        $wrapperClasses = $ctx->resolveVariantClasses($node, [
            'gap' => ['sm' => 'gap-2', 'base' => 'gap-3', 'lg' => 'gap-4'],
            'align' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'],
            'tone' => [
                'brand' => 'bg-accent-base text-text-on-accent',
                'neutral' => 'bg-surface-elevated text-text-base border border-border-base',
                'ghost' => 'bg-transparent text-text-base',
            ],
            'radius' => ['none' => 'rounded-none', 'button' => 'rounded-button', 'full' => 'rounded-full'],
            'size' => ['sm' => 'h-9 px-3 text-sm', 'base' => 'h-10 px-4 text-sm', 'lg' => 'h-12 px-5 text-base'],
        ]);

        $buttons = implode('', array_map(
            static function (string $network) use ($ctx, $node, $url, $title, $wrapperClasses): string {
                $label = ucfirst($network);
                return HtmlString::element('a', $ctx->containerAttrs($node, [
                    'href' => \esc_url(self::shareUrl($network, $url, $title)),
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                    'class' => trim('inline-flex items-center justify-center font-medium transition-colors ' . $wrapperClasses),
                ]), \esc_html($label))->toString();
            },
            $networks,
        ));

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'flex flex-wrap']), $buttons);
    }

    /** @return string[] */
    private static function parseNetworks(string $raw): array
    {
        $items = array_values(array_filter(array_map(
            static fn(string $item): string => strtolower(trim($item)),
            preg_split('/\s*,\s*|\r\n|\r|\n/', $raw) ?: [],
        )));

        return $items !== [] ? $items : ['facebook', 'x', 'linkedin'];
    }

    private static function shareUrl(string $network, string $url, string $title): string
    {
        $encodedUrl = rawurlencode($url);
        $encodedTitle = rawurlencode($title);

        return match ($network) {
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}",
            'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$encodedUrl}",
            'x', 'twitter' => "https://twitter.com/intent/tweet?url={$encodedUrl}&text={$encodedTitle}",
            'whatsapp' => "https://wa.me/?text={$encodedTitle}%20{$encodedUrl}",
            default => $url,
        };
    }
}