<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class CodeHighlightRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $code = (string) ($node->props['code'] ?? "const hello = 'Blocky';\nconsole.log(hello);");
        $language = trim((string) ($node->props['language'] ?? 'javascript'));
        $caption = trim((string) ($node->props['caption'] ?? ''));
        $showLineNumbers = (bool) ($node->props['showLineNumbers'] ?? true);

        $classes = $ctx->resolveVariantClasses($node, [
            'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'contrast' => 'bg-slate-950 text-slate-100'],
        ]);

        $escaped = esc_html($code);
        $lines = max(1, count(preg_split('/\r\n|\r|\n/', $code) ?: []));
        $numbers = '';
        if ($showLineNumbers) {
            for ($index = 1; $index <= $lines; $index += 1) {
                $numbers .= '<span>' . $index . '</span>';
            }
            $numbers = '<div aria-hidden="true" class="hidden shrink-0 select-none border-r border-white/10 px-3 py-4 text-right text-xs leading-6 opacity-60 md:grid">' . $numbers . '</div>';
        }

        $captionHtml = $caption !== '' ? '<figcaption class="border-b border-white/10 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] opacity-70">' . esc_html($caption) . '</figcaption>' : '';

        return HtmlString::element(
            'figure',
            $ctx->blockAttrs($node, ['class' => trim('overflow-hidden rounded-card ' . $classes)]),
            $captionHtml
            . '<div class="flex overflow-x-auto">'
            . $numbers
            . '<pre data-bky-code-highlight class="min-w-full overflow-x-auto px-4 py-4 text-sm leading-6"><code class="language-' . esc_attr($language) . '">' . $escaped . '</code></pre>'
            . '</div>'
        );
    }
}