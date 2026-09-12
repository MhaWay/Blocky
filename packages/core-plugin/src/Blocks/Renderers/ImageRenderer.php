<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class ImageRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $attachmentId = (int) ($node->props['attachmentId'] ?? 0);
        $alt          = (string) ($node->props['alt']  ?? '');
        $size         = (string) ($node->props['size'] ?? 'large');
        $loading      = self::loadingValue($node->props['loading'] ?? 'lazy');
        $decoding     = self::decodingValue($node->props['decoding'] ?? 'async');
        $fit          = self::fitValue($node->props['fit'] ?? 'cover');
        $focalX       = self::percentage($node->props['focalX'] ?? 50, 50);
        $focalY       = self::percentage($node->props['focalY'] ?? 50, 50);

        $layoutClasses = $ctx->resolveVariantClasses($node, [
            'align' => [
                'start'   => 'justify-start',
                'center'  => 'justify-center',
                'end'     => 'justify-end',
                'stretch' => 'justify-start',
            ],
        ]);

        $widthClasses = $ctx->resolveVariantClasses($node, [
            'width' => [
                'auto' => 'w-auto',
                'sm'   => 'w-64 max-w-full',
                'base' => 'w-96 max-w-full',
                'lg'   => 'w-[40rem] max-w-full',
                'full' => 'w-full',
            ],
        ]);

        if ($attachmentId <= 0) {
            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['class' => trim("flex {$layoutClasses}")]),
                '<div class="flex min-h-32 w-full items-center justify-center rounded-image border border-dashed border-border-base bg-surface-elevated text-sm text-text-muted">Select image</div>',
            );
        }

        $imageClasses = $ctx->resolveVariantClasses($node, [
            'rounded' => [
                'none' => '',
                'base' => 'rounded-image',
                'lg'   => 'rounded-2xl',
                'full' => 'rounded-full',
            ],
            'aspectRatio' => [
                'auto'   => '',
                'square' => 'aspect-square object-cover',
                'video'  => 'aspect-video object-cover',
                'wide'   => 'aspect-[21/9] object-cover',
            ],
        ]);

        $imageHtml = \wp_get_attachment_image(
            $attachmentId,
            $size,
            false,
            [
                'class'   => trim("h-auto {$widthClasses} {$imageClasses}"),
                'alt'     => $alt,
                'loading' => $loading,
                'decoding' => $decoding,
                'style' => sprintf('object-fit:%s;object-position:%d%% %d%%;', $fit, $focalX, $focalY),
            ]
        );

        return HtmlString::element(
            'figure',
            $ctx->blockAttrs($node, ['class' => trim("flex {$layoutClasses}")]),
            (string) $imageHtml,
        );
    }

    private static function loadingValue(mixed $value): string
    {
        return $value === 'eager' ? 'eager' : 'lazy';
    }

    private static function decodingValue(mixed $value): string
    {
        return match ($value) {
            'sync' => 'sync',
            'auto' => 'auto',
            default => 'async',
        };
    }

    private static function fitValue(mixed $value): string
    {
        return match ($value) {
            'contain' => 'contain',
            'fill' => 'fill',
            'none' => 'none',
            'scale-down' => 'scale-down',
            default => 'cover',
        };
    }

    private static function percentage(mixed $value, int $fallback): int
    {
        if (!is_numeric($value)) {
            return $fallback;
        }

        $number = (int) $value;

        if ($number < 0) {
            return 0;
        }

        if ($number > 100) {
            return 100;
        }

        return $number;
    }
}
