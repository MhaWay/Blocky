<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class VideoRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $url = (string) ($node->props['url'] ?? '');
        $title = (string) ($node->props['title'] ?? 'Embedded video');

        if ($url === '') {
            if (!$ctx->isEditorMode()) {
                return HtmlString::of('');
            }

            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['class' => 'flex aspect-video items-center justify-center rounded-image border border-dashed border-border-base bg-surface-elevated text-sm text-text-muted']),
                'Set video URL',
            );
        }

        $embedUrl = self::toEmbedUrl($url);
        if ($embedUrl === null) {
            return HtmlString::element(
                'video',
                $ctx->blockAttrs($node, ['class' => 'aspect-video w-full rounded-image bg-surface-sunken', 'src' => \esc_url($url), 'controls' => true]),
                '',
            );
        }

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['class' => 'aspect-video overflow-hidden rounded-image bg-surface-sunken']),
            '<iframe class="h-full w-full" src="' . \esc_url($embedUrl) . '" title="' . \esc_attr($title) . '" loading="lazy" allowfullscreen></iframe>',
        );
    }

    private static function toEmbedUrl(string $url): ?string
    {
        $parts = \wp_parse_url($url);
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        $path = (string) ($parts['path'] ?? '');

        if (str_contains($host, 'youtube.com') && isset($parts['query'])) {
            parse_str((string) $parts['query'], $query);
            $id = isset($query['v']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $query['v']) : '';
            return $id !== '' ? 'https://www.youtube.com/embed/' . $id : null;
        }

        if (str_contains($host, 'youtu.be')) {
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($path, '/'));
            return $id !== '' ? 'https://www.youtube.com/embed/' . $id : null;
        }

        if (str_contains($host, 'vimeo.com')) {
            $id = preg_replace('/[^0-9]/', '', trim($path, '/'));
            return $id !== '' ? 'https://player.vimeo.com/video/' . $id : null;
        }

        return null;
    }
}