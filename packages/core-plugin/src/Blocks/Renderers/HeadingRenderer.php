<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class HeadingRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $level      = max(1, min(6, (int) ($node->props['level'] ?? 2)));
        $text       = (string) ($node->props['text'] ?? '');
        $anchorId   = self::resolveAnchorId($node);
        $varClasses = $ctx->resolveVariantClasses($node, [
            'tone'  => ['default' => 'text-text-base', 'muted' => 'text-text-muted', 'accent' => 'text-accent-text'],
            'align' => ['start' => 'text-start', 'center' => 'text-center', 'end' => 'text-end'],
        ]);

        $sizeClasses = match ($level) {
            1 => 'text-5xl font-display font-bold leading-tight',
            2 => 'text-4xl font-display font-bold leading-tight',
            3 => 'text-3xl font-display font-semibold leading-snug',
            4 => 'text-2xl font-semibold leading-snug',
            5 => 'text-xl font-semibold',
            6 => 'text-lg font-semibold',
        };

        $tag     = "h{$level}";
        $classes = trim("{$sizeClasses} {$varClasses}");

        return HtmlString::element(
            $tag,
            $ctx->blockAttrs($node, ['class' => $classes, 'id' => $anchorId !== '' ? $anchorId : null]),
            wp_kses_post($text),
        );
    }

    private static function resolveAnchorId(Node $node): string
    {
        $configured = sanitize_title((string) ($node->props['anchorId'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $text = html_entity_decode(wp_strip_all_tags((string) ($node->props['text'] ?? '')), ENT_QUOTES, 'UTF-8');
        $slug = sanitize_title($text);
        return $slug !== '' ? $slug . '-' . substr($node->id, 0, 6) : '';
    }
}
