<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class BasicGalleryRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $items = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*|\r\n|\r|\n/', (string) ($node->props['items'] ?? '')) ?: [])));
        if ($items === []) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Add image IDs or URLs to build the gallery.' : '');
        }

        $columnsClass = $ctx->resolveVariantClasses($node, [
            'columns' => ['2' => 'grid-cols-2', '3' => 'grid-cols-3', '4' => 'grid-cols-4'],
            'gap' => ['sm' => 'gap-2', 'base' => 'gap-3', 'lg' => 'gap-4'],
        ]);

        $html = implode('', array_map(static function (string $item): string {
            $image = ctype_digit($item)
                ? (string) \wp_get_attachment_image((int) $item, 'medium', false, ['class' => 'h-auto w-full object-cover'])
                : '<img src="' . \esc_url($item) . '" alt="" class="h-auto w-full object-cover" loading="lazy" />';
            return '<div class="overflow-hidden rounded-base bg-surface-elevated">' . $image . '</div>';
        }, $items));

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => trim('grid ' . $columnsClass)]), $html);
    }
}