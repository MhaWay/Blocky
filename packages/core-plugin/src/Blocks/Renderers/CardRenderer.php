<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class CardRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $varClasses = $ctx->resolveVariantClasses($node, [
            'background' => [
                'base'     => 'bg-surface-base',
                'elevated' => 'bg-surface-elevated',
                'accent'   => 'bg-accent-subtle',
            ],
            'shadow' => [
                'none' => 'shadow-none',
                'sm'   => 'shadow-sm',
                'base' => 'shadow',
                'md'   => 'shadow-md',
                'lg'   => 'shadow-lg',
            ],
            'overflow' => [
                'hidden'  => 'overflow-hidden',
                'visible' => 'overflow-visible',
            ],
        ]);

        $border = (bool) ($node->props['border'] ?? true) ? 'border border-border-subtle' : '';
        $attrs = $ctx->containerAttrs($node, ['class' => trim("rounded-card {$border} {$varClasses}")]);

        return HtmlString::element('div', $attrs, self::renderRegions($node, $ctx));
    }

    private static function renderRegions(Node $node, RenderContext $ctx): string
    {
        $regions = [
            'media'   => ['label' => 'Drop media here', 'class' => 'bky-card-slot bky-card-media'],
            'header'  => ['label' => 'Drop header here', 'class' => 'bky-card-slot bky-card-header border-b border-border-subtle'],
            'default' => ['label' => 'Drop content here', 'class' => 'bky-card-slot bky-card-body flex flex-col gap-4'],
            'footer'  => ['label' => 'Drop footer actions here', 'class' => 'bky-card-slot bky-card-footer flex flex-wrap items-center gap-3 border-t border-border-subtle'],
        ];

        $html = '';
        foreach ($regions as $slotName => $config) {
            $slotHtml = $ctx->renderSlot($node, $slotName)->toString();
            if ($slotHtml === '' && !$ctx->isEditorMode()) {
                continue;
            }

            $classes = trim($config['class'] . ' ' . self::regionPaddingClass($node, $slotName));
            $slotAttrs = [
                'class' => $classes,
            ];
            if ($ctx->isEditorMode()) {
                $slotAttrs['data-bky-slot-label'] = $config['label'];
            }

            $attrs = $ctx->slotAttrs($node, $slotName, $slotAttrs);
            $html .= '<div' . HtmlString::attrs($attrs) . '>' . $slotHtml . '</div>';
        }

        return $html;
    }

    private static function regionPaddingClass(Node $node, string $slotName): string
    {
        if ($slotName === 'media') {
            return '';
        }

        $value = (string) ($node->variants['padding'] ?? $node->props['padding'] ?? 'base');
        return match ($value) {
            'none' => 'p-0',
            'sm'   => 'p-4',
            'lg'   => 'p-8',
            default => 'p-6',
        };
    }
}