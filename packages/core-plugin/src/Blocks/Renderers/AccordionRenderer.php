<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class AccordionRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $items = self::parseItems((string) ($node->props['items'] ?? "Question 1|Answer one\nQuestion 2|Answer two"));
        $openFirst = (bool) ($node->props['openFirst'] ?? true);

        $html = '';
        foreach ($items as $index => $item) {
            $html .= '<details class="rounded-card border border-border-subtle bg-surface-base p-4"' . (($index === 0 && $openFirst) ? ' open' : '') . '>'
                . '<summary class="cursor-pointer list-none text-sm font-semibold">' . \esc_html($item['title']) . '</summary>'
                . '<div class="pt-3 text-sm leading-6 text-text-muted">' . \esc_html($item['content']) . '</div>'
                . '</details>';
        }

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'space-y-3']), $html);
    }

    /** @return array<int, array{title:string, content:string}> */
    private static function parseItems(string $raw): array
    {
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            [$title, $content] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $items[] = ['title' => $title !== '' ? $title : 'Question', 'content' => $content !== '' ? $content : 'Answer'];
        }
        return $items !== [] ? $items : [['title' => 'Question', 'content' => 'Answer']];
    }
}