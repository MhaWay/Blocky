<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class ImageGalleryRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $items = self::parseItems((string) ($node->props['items'] ?? ''));
        if ($items === []) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Add image IDs or URLs to build the gallery.' : '');
        }

        $classes = $ctx->resolveVariantClasses($node, [
            'columns' => [
                '2' => 'grid-cols-1 md:grid-cols-2',
                '3' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
                '4' => 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4',
            ],
            'gap' => [
                'sm' => 'gap-3',
                'base' => 'gap-4',
                'lg' => 'gap-6',
            ],
            'aspectRatio' => [
                'square' => 'aspect-square',
                'video' => 'aspect-video',
                'wide' => 'aspect-[21/9]',
            ],
        ]);

        $html = implode('', array_map(
            static fn(string $item): string => '<figure class="overflow-hidden rounded-image border border-border-subtle bg-surface-elevated">' . self::renderImage($item) . '</figure>',
            $items,
        ));

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => trim('grid ' . $classes)]), $html);
    }

    /** @return string[] */
    private static function parseItems(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\s*,\s*|\r\n|\r|\n/', $raw) ?: [])));
    }

    private static function renderImage(string $item): string
    {
        if (ctype_digit($item)) {
            return (string) \wp_get_attachment_image((int) $item, 'large', false, ['class' => 'h-full w-full object-cover']);
        }

        return '<img src="' . \esc_url($item) . '" alt="" class="h-full w-full object-cover" loading="lazy" />';
    }
}