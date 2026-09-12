<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class NavMenuRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $menuLocation = trim((string) ($node->props['menuLocation'] ?? ''));
        $fallbackItems = self::parseItems((string) ($node->props['items'] ?? "Home|/\nAbout|/about\nContact|/contact"));

        $listClasses = $ctx->resolveVariantClasses($node, [
            'layout' => ['row' => 'flex-row flex-wrap', 'column' => 'flex-col'],
            'gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6'],
            'align' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'],
        ]);

        $menuHtml = '';
        if ($menuLocation !== '' && \function_exists('wp_nav_menu')) {
            $menuHtml = (string) \wp_nav_menu([
                'theme_location' => $menuLocation,
                'container' => false,
                'echo' => false,
                'fallback_cb' => false,
                'menu_class' => trim('flex list-none pl-0 ' . $listClasses),
            ]);
        }

        if ($menuHtml === '') {
            $itemsHtml = implode('', array_map(
                static fn(array $item): string => '<li><a href="' . \esc_url($item['href']) . '" class="inline-flex items-center text-sm font-medium text-text-base hover:text-accent-text">' . \esc_html($item['label']) . '</a></li>',
                $fallbackItems,
            ));
            $menuHtml = '<ul class="flex list-none pl-0 ' . \esc_attr($listClasses) . '">' . $itemsHtml . '</ul>';
        }

        return HtmlString::element('nav', $ctx->blockAttrs($node, ['aria-label' => 'Navigation']), $menuHtml);
    }

    /** @return array<int, array{label:string, href:string}> */
    private static function parseItems(string $raw): array
    {
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;

            [$label, $href] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $items[] = [
                'label' => $label !== '' ? $label : 'Link',
                'href' => $href !== '' ? $href : '#',
            ];
        }

        return $items !== [] ? $items : [['label' => 'Home', 'href' => '/']];
    }
}