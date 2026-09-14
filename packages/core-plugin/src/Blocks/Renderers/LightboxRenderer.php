<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class LightboxRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $overlayId = trim((string) ($node->props['overlayId'] ?? ''));
        if ($overlayId === '') {
            $overlayId = 'lightbox-' . $node->id;
        }

        $mediaUrl = trim((string) ($node->props['mediaUrl'] ?? ''));
        $mediaType = trim((string) ($node->props['mediaType'] ?? 'image'));
        $caption = trim((string) ($node->props['caption'] ?? ''));
        $triggerLabel = trim((string) ($node->props['triggerLabel'] ?? 'Open media'));
        $thumbnailUrl = trim((string) ($node->props['thumbnailUrl'] ?? ''));
        $aspectRatioClass = $ctx->resolveVariantClasses($node, ['aspectRatio' => ['square' => 'aspect-square', 'video' => 'aspect-video', 'wide' => 'aspect-[21/9]']]);

        if ($mediaUrl === '') {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => trim('flex items-center justify-center rounded-card border border-dashed border-border-base bg-surface-elevated text-sm text-text-muted ' . $aspectRatioClass)]), $ctx->isEditorMode() ? 'Add a media URL to render this lightbox.' : '');
        }

        $triggerContent = $thumbnailUrl !== ''
            ? '<img src="' . esc_url($thumbnailUrl) . '" alt="' . esc_attr($caption !== '' ? $caption : $triggerLabel) . '" class="h-full w-full object-cover" />'
            : '<span class="inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-3 text-sm font-medium text-text-on-accent">' . esc_html($triggerLabel) . '</span>';

        $media = match ($mediaType) {
            'video' => '<video controls class="max-h-[80vh] w-full rounded-xl bg-black"><source src="' . esc_url($mediaUrl) . '" /></video>',
            'iframe' => '<iframe src="' . esc_url($mediaUrl) . '" class="h-[70vh] w-full rounded-xl border-0 bg-white" allowfullscreen loading="lazy"></iframe>',
            default => '<img src="' . esc_url($mediaUrl) . '" alt="' . esc_attr($caption !== '' ? $caption : $triggerLabel) . '" class="max-h-[80vh] w-full rounded-xl object-contain" />',
        };

        $overlay = '<div data-bky-overlay-id="' . esc_attr($overlayId) . '" data-bky-overlay-variant="lightbox" data-bky-placement="center" data-bky-dismiss-esc="true" data-bky-dismiss-outside="true" data-bky-focus-trap="true" role="dialog" aria-modal="true" aria-hidden="true" tabindex="-1" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">'
            . '<div data-bky-overlay-panel class="pointer-events-auto relative w-full max-w-5xl rounded-2xl bg-black p-4 text-white shadow-2xl">'
            . '<button type="button" data-bky-action="overlay.close" data-bky-target="' . esc_attr($overlayId) . '" data-bky-event="click" class="absolute right-3 top-3 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Close lightbox">×</button>'
            . $media
            . ($caption !== '' ? '<p class="mt-3 text-sm text-white/80">' . esc_html($caption) . '</p>' : '')
            . '</div></div>';

        $trigger = '<button type="button" data-bky-action="overlay.open" data-bky-target="' . esc_attr($overlayId) . '" data-bky-event="click" class="group block w-full overflow-hidden rounded-card border border-border-subtle bg-surface-base ' . esc_attr($aspectRatioClass) . '">' . $triggerContent . '</button>';

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'space-y-3']), $trigger . $overlay);
    }
}