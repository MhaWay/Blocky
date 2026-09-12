<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class GridRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $varClasses = $ctx->resolveVariantClasses($node, [
            'columns' => [
                '1' => 'grid-cols-1',
                '2' => 'grid-cols-1 sm:grid-cols-2',
                '3' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
                '4' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
                '5' => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
                '6' => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
                '7' => 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-7',
                '8' => 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-8',
                '9' => 'grid-cols-3 lg:grid-cols-9',
                '10' => 'grid-cols-2 sm:grid-cols-5 lg:grid-cols-10',
                '11' => 'grid-cols-3 sm:grid-cols-6 lg:grid-cols-11',
                '12' => 'grid-cols-3 sm:grid-cols-6 lg:grid-cols-12',
            ],
            'rows' => [
                '1' => 'grid-rows-1',
                '2' => 'grid-rows-2',
                '3' => 'grid-rows-3',
                '4' => 'grid-rows-4',
                '5' => 'grid-rows-5',
                '6' => 'grid-rows-6',
            ],
            'gap' => [
                'none' => 'gap-0',
                'sm'   => 'gap-4',
                'base' => 'gap-6',
                'lg'   => 'gap-8',
                'xl'   => 'gap-10',
            ],
            'rowGap' => [
                'none' => 'gap-y-0',
                'sm'   => 'gap-y-4',
                'base' => 'gap-y-6',
                'lg'   => 'gap-y-8',
                'xl'   => 'gap-y-10',
            ],
            'alignItems' => [
                'start'   => 'items-start',
                'center'  => 'items-center',
                'end'     => 'items-end',
                'stretch' => 'items-stretch',
            ],
            'justifyItems' => [
                'start'   => 'justify-items-start',
                'center'  => 'justify-items-center',
                'end'     => 'justify-items-end',
                'stretch' => 'justify-items-stretch',
            ],
            'background' => [
                'transparent' => '',
                'surface'     => 'bg-surface-base',
                'elevated'    => 'bg-surface-elevated',
                'accent'      => 'bg-accent-subtle',
                'dark'        => 'bg-text-base',
            ],
            'textColor' => [
                'inherit' => '',
                'base'    => 'text-text-base',
                'muted'   => 'text-text-muted',
                'inverse' => 'text-text-inverse',
                'accent'  => 'text-accent-text',
            ],
            'border' => [
                'none'   => '',
                'subtle' => 'border border-border-subtle',
                'base'   => 'border border-border-base',
                'strong' => 'border border-border-strong',
                'accent' => 'border border-accent-base',
            ],
            'radius' => [
                'none' => 'rounded-none',
                'sm'   => 'rounded-sm',
                'base' => 'rounded-base',
                'lg'   => 'rounded-lg',
                'xl'   => 'rounded-xl',
            ],
            'shadow' => [
                'none' => 'shadow-none',
                'sm'   => 'shadow-sm',
                'base' => 'shadow',
                'md'   => 'shadow-md',
                'lg'   => 'shadow-lg',
            ],
            'overflow' => [
                'visible' => 'overflow-visible',
                'hidden'  => 'overflow-hidden',
            ],
        ]);

        $attrs = $ctx->containerAttrs($node, ['class' => trim("grid {$varClasses}")]);

        if (!$ctx->isEditorMode()) {
            $inner = $ctx->renderSlot($node)->toString();
            $attrs = $ctx->slotAttrs($node, 'default', $attrs);

            return HtmlString::element(
                'div',
                $attrs,
                $inner,
            );
        }

        $children = $node->slots['default'] ?? [];
        $columns = self::boundedInteger($node->props['columns'] ?? null, 3, 1, 12);
        $configuredRows = self::boundedInteger($node->props['rows'] ?? null, 1, 1, 12);
        $implicitRows = max(1, (int) ceil(count($children) / max(1, $columns)));
        $occupants = [];
        $placedRows = 1;

        foreach ($children as $index => $childId) {
            $child = $ctx->getNode($childId);
            $position = self::gridPositionForNode($child, $index, $columns);
            $occupants[self::positionKey($position['row'], $position['column'])] = $childId;
            $placedRows = max($placedRows, $position['row']);
        }

        $rows = max($configuredRows, $implicitRows, $placedRows);
        $cells = [];

        for ($row = 1; $row <= $rows; $row++) {
            for ($column = 1; $column <= $columns; $column++) {
                $childId = $occupants[self::positionKey($row, $column)] ?? null;
                $cellAttrs = $ctx->slotAttrs($node, 'default', [
                    'class' => $childId === null ? 'bky-grid-cell bky-edit-empty-slot' : 'bky-grid-cell',
                    'style' => "grid-column-start: {$column}; grid-row-start: {$row}",
                    'data-bky-grid-column' => (string) $column,
                    'data-bky-grid-row' => (string) $row,
                ]);

                $cellInner = '';
                if (is_string($childId)) {
                    $childNode = $ctx->getNode($childId);
                    if ($childNode !== null) {
                        $cellInner = $ctx->renderNode($childNode)->toString();
                    }
                }

                $cells[] = HtmlString::element('div', $cellAttrs, $cellInner)->toString();
            }
        }

        return HtmlString::element(
            'div',
            $attrs,
            implode('', $cells),
        );
    }

    /**
     * @return array{row: int, column: int}
     */
    private static function gridPositionForNode(?Node $node, int $index, int $columns): array
    {
        $fallbackColumn = ($index % $columns) + 1;
        $fallbackRow = (int) floor($index / $columns) + 1;

        return [
            'column' => self::boundedInteger($node !== null ? ($node->props['gridColumnStart'] ?? null) : null, $fallbackColumn, 1, $columns),
            'row' => self::boundedInteger($node !== null ? ($node->props['gridRowStart'] ?? null) : null, $fallbackRow, 1, 12),
        ];
    }

    private static function positionKey(int $row, int $column): string
    {
        return $row . ':' . $column;
    }

    private static function boundedInteger(mixed $value, int $fallback, int $min, int $max): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if (!is_int($parsed)) {
            return $fallback;
        }

        return max($min, min($max, $parsed));
    }
}
