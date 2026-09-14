<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class SectionRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $fullWidth = (bool) ($node->props['fullWidth'] ?? false);
        $inner     = $ctx->renderSlot($node)->toString();

        $rootClasses = implode(' ', array_filter([
            'bky-section',
            self::classFrom($node, 'paddingY', ['none' => '', 'sm' => 'py-8', 'base' => 'py-12', 'lg' => 'py-20', 'xl' => 'py-24', '2xl' => 'py-32'], 'lg'),
            self::classFrom($node, 'background', ['transparent' => '', 'surface' => 'bg-surface-base', 'elevated' => 'bg-surface-elevated', 'sunken' => 'bg-surface-sunken', 'accent' => 'bg-accent-subtle', 'dark' => 'bg-text-base'], 'transparent'),
            self::classFrom($node, 'textColor', ['base' => 'text-text-base', 'muted' => 'text-text-muted', 'inverse' => 'text-text-inverse', 'accent' => 'text-accent-text'], 'base'),
            self::classFrom($node, 'minHeight', ['auto' => '', 'screen25' => 'min-h-[25vh]', 'screen50' => 'min-h-[50vh]', 'screen75' => 'min-h-[75vh]', 'screen' => 'min-h-screen'], 'auto'),
            self::classFrom($node, 'border', ['none' => '', 'subtle' => 'border border-border-subtle', 'base' => 'border border-border-base', 'strong' => 'border border-border-strong', 'accent' => 'border border-accent-base'], 'none'),
            self::classFrom($node, 'radius', ['none' => 'rounded-none', 'sm' => 'rounded-sm', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'xl' => 'rounded-xl'], 'none'),
            self::classFrom($node, 'shadow', ['none' => 'shadow-none', 'sm' => 'shadow-sm', 'base' => 'shadow', 'md' => 'shadow-md', 'lg' => 'shadow-lg'], 'none'),
            self::classFrom($node, 'overflow', ['visible' => 'overflow-visible', 'hidden' => 'overflow-hidden'], 'visible'),
            self::safeClassList((string) ($node->props['customClass'] ?? '')),
            RenderContext::nodeUtilityClasses($node),
        ]));

        $containerClass = implode(' ', array_filter([
            'flex flex-col',
            $fullWidth ? 'w-full' : self::classFrom($node, 'contentWidth', ['narrow' => 'max-w-3xl mx-auto', 'container' => 'container mx-auto', 'wide' => 'max-w-7xl mx-auto', 'full' => 'w-full'], 'container'),
            self::classFrom($node, 'paddingX', ['none' => 'px-0', 'sm' => 'px-4', 'base' => 'px-6', 'lg' => 'px-8', 'xl' => 'px-12'], 'base'),
            self::classFrom($node, 'verticalAlign', ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'], 'start'),
            self::classFrom($node, 'horizontalAlign', ['start' => 'items-start text-left', 'center' => 'items-center text-center', 'end' => 'items-end text-right', 'stretch' => 'items-stretch'], 'start'),
            self::classFrom($node, 'gap', ['none' => 'gap-0', 'sm' => 'gap-4', 'base' => 'gap-6', 'lg' => 'gap-8', 'xl' => 'gap-12'], 'base'),
        ]));

        $slotAttrs = \Blocky\Core\Support\HtmlString::attrs(
            $ctx->slotAttrs($node, 'default', ['class' => $containerClass])
        );

        return HtmlString::element(
            'section',
            $ctx->containerAttrs($node, ['class' => trim($rootClasses)]),
            "<div{$slotAttrs}>{$inner}</div>",
        );
    }

    /**
     * @param array<string, string> $map
     */
    private static function classFrom(Node $node, string $key, array $map, string $default): string
    {
        $value = (string) ($node->variants[$key] ?? $node->props[$key] ?? $default);
        return $map[$value] ?? $map[$default] ?? '';
    }

    private static function safeClassList(string $classList): string
    {
        $classes = preg_split('/\s+/', trim($classList)) ?: [];
        $safe = array_filter(array_map(
            static fn(string $className): string => preg_replace('/[^A-Za-z0-9_:-]/', '', $className) ?? '',
            $classes,
        ));
        return implode(' ', $safe);
    }
}
