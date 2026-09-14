<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class TabsRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $items = self::parseItems((string) ($node->props['items'] ?? "Tab 1|First tab content\nTab 2|Second tab content"));
        $activeIndex = max(0, min(count($items) - 1, (int) ($node->props['activeIndex'] ?? 0)));
        $groupId = 'bky-tabs-' . $node->id;

        $buttons = '';
        $panels = '';
        foreach ($items as $index => $item) {
            $isActive = $index === $activeIndex;
            $buttons .= '<button type="button" role="tab" data-bky-tab="' . $index . '" aria-selected="' . ($isActive ? 'true' : 'false') . '" class="rounded-button px-4 py-2 text-sm font-medium ' . ($isActive ? 'bg-accent-base text-text-on-accent' : 'bg-surface-elevated text-text-base') . '">' . \esc_html($item['title']) . '</button>';
            $panels .= '<div role="tabpanel" data-bky-panel="' . $index . '"' . ($isActive ? '' : ' hidden') . ' class="rounded-card border border-border-subtle bg-surface-base p-5 text-sm leading-6 text-text-base">' . \esc_html($item['content']) . '</div>';
        }

        $script = $ctx->isEditorMode() ? '' : '<script>(function(){var root=document.currentScript.closest("[data-bky-tabs-root]");if(!root)return;root.querySelectorAll("[data-bky-tab]").forEach(function(button){button.addEventListener("click",function(){var index=button.getAttribute("data-bky-tab");root.querySelectorAll("[data-bky-tab]").forEach(function(tab){var active=tab.getAttribute("data-bky-tab")===index;tab.setAttribute("aria-selected",active?"true":"false");tab.className="rounded-button px-4 py-2 text-sm font-medium "+(active?"bg-accent-base text-text-on-accent":"bg-surface-elevated text-text-base");});root.querySelectorAll("[data-bky-panel]").forEach(function(panel){panel.hidden=panel.getAttribute("data-bky-panel")!==index;});});});})();</script>';

        return HtmlString::element('div', $ctx->blockAttrs($node, ['data-bky-tabs-root' => $groupId, 'class' => 'space-y-4']), '<div class="flex flex-wrap gap-2" role="tablist">' . $buttons . '</div><div class="space-y-3">' . $panels . '</div>' . $script);
    }

    /** @return array<int, array{title:string, content:string}> */
    private static function parseItems(string $raw): array
    {
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            [$title, $content] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $items[] = ['title' => $title !== '' ? $title : 'Tab', 'content' => $content !== '' ? $content : 'Tab content'];
        }
        return $items !== [] ? $items : [['title' => 'Tab 1', 'content' => 'Tab content']];
    }
}