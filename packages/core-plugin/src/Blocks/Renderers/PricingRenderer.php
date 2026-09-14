<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class PricingRenderer implements BlockRendererInterface
{
    public function __construct(private readonly string $kind) {}

    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        return $this->kind === 'list'
            ? $this->renderList($node, $ctx)
            : $this->renderTable($node, $ctx);
    }

    private function renderTable(Node $node, RenderContext $ctx): HtmlString
    {
        $title = trim((string) ($node->props['title'] ?? 'Pricing'));
        $subtitle = trim((string) ($node->props['subtitle'] ?? ''));
        $price = trim((string) ($node->props['price'] ?? '0'));
        $currency = trim((string) ($node->props['currency'] ?? '€'));
        $cadence = trim((string) ($node->props['cadence'] ?? ''));
        $features = self::parseLines((string) ($node->props['features'] ?? ''));
        $buttonLabel = trim((string) ($node->props['buttonLabel'] ?? ''));
        $buttonUrl = trim((string) ($node->props['buttonUrl'] ?? ''));
        $featured = (bool) ($node->props['featured'] ?? false);

        $classes = $ctx->resolveVariantClasses($node, [
            'align' => ['start' => 'items-start text-left', 'center' => 'items-center text-center'],
            'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base', 'contrast' => 'bg-text-base text-text-on-accent'],
        ]);

        $badge = $featured ? '<span class="rounded-full bg-accent-base px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-text-on-accent">Popular</span>' : '';
        $featureItems = implode('', array_map(static fn(string $feature): string => '<li class="flex items-start gap-2"><span class="mt-1 text-accent-base">•</span><span>' . \esc_html($feature) . '</span></li>', $features));
        $button = $buttonLabel !== ''
            ? '<a href="' . \esc_url($buttonUrl !== '' ? $buttonUrl : '#') . '" class="inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-2 text-sm font-medium text-text-on-accent">' . \esc_html($buttonLabel) . '</a>'
            : '';

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['class' => trim('flex flex-col gap-5 rounded-card p-6 ' . $classes)]),
            $badge
            . '<div class="space-y-2">'
            . '<h3 class="text-2xl font-semibold">' . \esc_html($title) . '</h3>'
            . ($subtitle !== '' ? '<p class="text-sm opacity-75">' . \esc_html($subtitle) . '</p>' : '')
            . '</div>'
            . '<div class="flex items-end gap-1"><span class="text-sm opacity-70">' . \esc_html($currency) . '</span><span class="text-5xl font-semibold leading-none">' . \esc_html($price) . '</span>' . ($cadence !== '' ? '<span class="pb-1 text-sm opacity-70">' . \esc_html($cadence) . '</span>' : '') . '</div>'
            . ($featureItems !== '' ? '<ul class="space-y-3 text-sm leading-6">' . $featureItems . '</ul>' : '')
            . ($button !== '' ? '<div class="pt-2">' . $button . '</div>' : '')
        );
    }

    private function renderList(Node $node, RenderContext $ctx): HtmlString
    {
        $items = self::parsePriceListItems((string) ($node->props['items'] ?? ''));
        $showDividers = (bool) ($node->props['showDividers'] ?? true);
        $classes = $ctx->resolveVariantClasses($node, [
            'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base'],
        ]);

        if ($items === []) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Add price list items to build the block.' : '');
        }

        $rows = '';
        foreach ($items as $index => $item) {
            $divider = $showDividers && $index > 0 ? 'border-t border-border-subtle' : '';
            $rows .= '<div class="' . \esc_attr(trim('flex flex-col gap-2 py-4 md:flex-row md:items-start md:justify-between ' . $divider)) . '">'
                . '<div class="space-y-1">'
                . '<div class="font-semibold">' . \esc_html($item['name']) . '</div>'
                . ($item['description'] !== '' ? '<div class="text-sm opacity-75">' . \esc_html($item['description']) . '</div>' : '')
                . '</div>'
                . '<div class="shrink-0 text-lg font-semibold">' . \esc_html($item['price']) . '</div>'
                . '</div>';
        }

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => trim('rounded-card p-6 ' . $classes)]), $rows);
    }

    /** @return string[] */
    private static function parseLines(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
    }

    /** @return array<int, array{name:string, description:string, price:string}> */
    private static function parsePriceListItems(string $raw): array
    {
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$name, $description, $price] = array_pad(array_map('trim', explode('|', $line, 3)), 3, '');
            if ($name === '') {
                continue;
            }
            $items[] = ['name' => $name, 'description' => $description, 'price' => $price !== '' ? $price : ''];
        }
        return $items;
    }
}