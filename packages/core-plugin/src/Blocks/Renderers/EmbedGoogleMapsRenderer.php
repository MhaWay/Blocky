<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class EmbedGoogleMapsRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $provider = (string) ($node->props['provider'] ?? 'google');
        $query = trim((string) ($node->props['query'] ?? ''));
        $latitude = trim((string) ($node->props['latitude'] ?? ''));
        $longitude = trim((string) ($node->props['longitude'] ?? ''));
        $zoom = max(1, min(18, (int) ($node->props['zoom'] ?? 12)));
        $title = trim((string) ($node->props['title'] ?? 'Map embed'));
        $allowFullscreen = (bool) ($node->props['allowFullscreen'] ?? true);

        $classes = $ctx->resolveVariantClasses($node, [
            'height' => ['sm' => 'min-h-64', 'base' => 'min-h-80', 'lg' => 'min-h-[30rem]'],
            'aspectRatio' => ['square' => 'aspect-square', 'video' => 'aspect-video', 'wide' => 'aspect-[21/9]'],
        ]);

        $src = self::mapSrc($provider, $query, $latitude, $longitude, $zoom);
        if ($src === null) {
            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['class' => trim('flex items-center justify-center rounded-card border border-dashed border-border-base bg-surface-elevated text-sm text-text-muted ' . $classes)]),
                $ctx->isEditorMode() ? 'Add a map query or coordinates to render this block.' : '',
            );
        }

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['class' => trim('overflow-hidden rounded-card border border-border-subtle bg-surface-base ' . $classes)]),
            '<iframe class="h-full w-full" src="' . esc_url($src) . '" title="' . esc_attr($title) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade"' . ($allowFullscreen ? ' allowfullscreen' : '') . '></iframe>'
        );
    }

    private static function mapSrc(string $provider, string $query, string $latitude, string $longitude, int $zoom): ?string
    {
        if ($provider === 'openstreetmap') {
            if ($latitude === '' || $longitude === '' || !is_numeric($latitude) || !is_numeric($longitude)) {
                return null;
            }

            $lat = (float) $latitude;
            $lng = (float) $longitude;
            $delta = max(0.01, 0.4 / max(1, $zoom));
            $left = $lng - $delta;
            $right = $lng + $delta;
            $top = $lat + $delta;
            $bottom = $lat - $delta;

            return 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode("{$left},{$bottom},{$right},{$top}") . '&layer=mapnik&marker=' . rawurlencode("{$lat},{$lng}");
        }

        if ($latitude !== '' && $longitude !== '' && is_numeric($latitude) && is_numeric($longitude)) {
            return 'https://www.google.com/maps?q=' . rawurlencode($latitude . ',' . $longitude) . '&z=' . $zoom . '&output=embed';
        }

        if ($query === '') {
            return null;
        }

        return 'https://www.google.com/maps?q=' . rawurlencode($query) . '&z=' . $zoom . '&output=embed';
    }
}