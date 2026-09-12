<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class OverlayRenderer implements BlockRendererInterface
{
    public function __construct(
        private readonly string $variant,
    ) {}

    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $overlayId = trim((string) ($node->props['overlayId'] ?? ''));
        if ($overlayId === '') {
            $overlayId = str_replace(['bky/', '/'], ['', '-'], $node->type) . '-' . $node->id;
        }

        $placement = trim((string) ($node->props['placement'] ?? self::defaultPlacement($this->variant)));
        $size = trim((string) ($node->props['size'] ?? 'md'));
        $backdrop = trim((string) ($node->props['backdrop'] ?? self::defaultBackdrop($this->variant)));
        $title = trim((string) ($node->props['title'] ?? ''));
        $description = trim((string) ($node->props['description'] ?? ''));
        $openOn = trim((string) ($node->props['openOn'] ?? ''));
        $frequency = trim((string) ($node->props['frequency'] ?? 'always'));
        $closeOn = trim((string) ($node->props['closeOn'] ?? ''));
        $defaultOpen = (bool) ($node->props['defaultOpen'] ?? false);
        $dismissEsc = (bool) ($node->props['dismissEsc'] ?? true);
        $dismissOutside = (bool) ($node->props['dismissOutside'] ?? true);
        $focusTrap = (bool) ($node->props['focusTrap'] ?? self::blocksBackgroundInteraction($this->variant));
        $titleId = $title !== '' ? $overlayId . '-title' : null;
        $descriptionId = $description !== '' ? $overlayId . '-description' : null;

        $rootClasses = $ctx->isEditorMode()
            ? self::editorRootClasses($this->variant)
            : self::publicRootClasses($this->variant, $placement, $backdrop);
        $panelClasses = self::panelClasses($this->variant, $placement, $size, $ctx->isEditorMode());
        $cookieName = trim((string) ($node->props['cookieName'] ?? ''));
        $acceptLabel = trim((string) ($node->props['acceptLabel'] ?? 'Accept'));
        $rejectLabel = trim((string) ($node->props['rejectLabel'] ?? 'Reject'));
        $cookieDurationDays = max(1, (int) ($node->props['cookieDurationDays'] ?? 180));

        $headerHtml = $this->regionHtml($node, $ctx, 'header', 'Drop overlay header here', 'px-5 py-4 border-b border-border-subtle', $title !== '' ? '<h3 id="' . \esc_attr((string) $titleId) . '" class="text-lg font-semibold text-text-base">' . \esc_html($title) . '</h3>' : '', $titleId);
        $bodyFallback = $this->variant === 'command-palette'
            ? self::commandPaletteBodyHtml($overlayId, $description)
            : ($description !== '' ? '<p>' . \esc_html($description) . '</p>' : '');
        $bodyHtml = $this->regionHtml($node, $ctx, 'body', 'Drop overlay body here', 'px-5 py-5 text-sm leading-6 text-text-muted', $bodyFallback, $descriptionId);
        $footerFallback = '';
        if ($this->variant === 'dialog-confirm') {
            $footerFallback = self::dialogActionsHtml($overlayId, (string) ($node->props['cancelLabel'] ?? 'Cancel'), (string) ($node->props['confirmLabel'] ?? 'Confirm'));
        } elseif ($this->variant === 'cookie-banner') {
            $footerFallback = self::cookieActionsHtml($overlayId, $acceptLabel, $rejectLabel);
        }
        $footerHtml = $this->regionHtml($node, $ctx, 'footer', 'Drop overlay footer here', 'px-5 py-4 border-t border-border-subtle', $footerFallback);
        $closeHtml = $this->closeRegionHtml($node, $ctx, $overlayId);

        $panelInner = $closeHtml . $headerHtml . $bodyHtml . $footerHtml;
        $panel = HtmlString::element('div', [
            'data-bky-overlay-panel' => '1',
            'tabindex' => '-1',
            'class' => $panelClasses,
        ], $panelInner)->toString();

        return HtmlString::element('div', $ctx->containerAttrs($node, [
            'data-bky-overlay-id' => $overlayId,
            'data-bky-overlay-variant' => $this->variant,
            'data-bky-placement' => $placement,
            'data-bky-open-on' => $openOn !== '' ? $openOn : null,
            'data-bky-frequency' => $frequency !== '' ? $frequency : 'always',
            'data-bky-close-on' => $closeOn !== '' ? $closeOn : null,
            'data-bky-default-open' => $defaultOpen ? 'true' : 'false',
            'data-bky-dismiss-esc' => $dismissEsc ? 'true' : 'false',
            'data-bky-dismiss-outside' => $dismissOutside ? 'true' : 'false',
            'data-bky-focus-trap' => $focusTrap ? 'true' : 'false',
            'data-bky-cookie-name' => $cookieName !== '' ? $cookieName : null,
            'data-bky-cookie-duration' => $this->variant === 'cookie-banner' ? (string) $cookieDurationDays : null,
            'role' => self::ariaRole($this->variant),
            'aria-modal' => self::blocksBackgroundInteraction($this->variant) ? 'true' : 'false',
            'aria-label' => $title !== '' ? $title : ucfirst(str_replace('-', ' ', $this->variant)),
            'aria-labelledby' => $titleId,
            'aria-describedby' => $descriptionId,
            'aria-hidden' => (!$ctx->isEditorMode() && !$defaultOpen) ? 'true' : 'false',
            'tabindex' => '-1',
            'hidden' => (!$ctx->isEditorMode() && !$defaultOpen),
            'class' => $rootClasses,
        ]), $panel);
    }

    private function closeRegionHtml(Node $node, RenderContext $ctx, string $overlayId): string
    {
        $slotHtml = $ctx->renderSlot($node, 'close')->toString();
        if ($slotHtml === '' && !$ctx->isEditorMode() && $this->variant !== 'tooltip') {
            $slotHtml = self::defaultCloseButton($overlayId);
        }
        if ($slotHtml === '' && !$ctx->isEditorMode()) {
            return '';
        }

        $slotAttrs = [
            'class' => 'absolute right-3 top-3 z-10',
        ];
        if ($ctx->isEditorMode()) {
            $slotAttrs['data-bky-slot-label'] = 'Drop close control here';
        }

        return '<div' . HtmlString::attrs($ctx->slotAttrs($node, 'close', $slotAttrs)) . '>' . $slotHtml . '</div>';
    }

    private function regionHtml(Node $node, RenderContext $ctx, string $slotName, string $slotLabel, string $classes, string $fallback, ?string $id = null): string
    {
        $slotHtml = $ctx->renderSlot($node, $slotName)->toString();
        if ($slotHtml === '' && !$ctx->isEditorMode()) {
            $slotHtml = $fallback;
        }

        if ($slotHtml === '' && !$ctx->isEditorMode()) {
            return '';
        }

        $slotAttrs = ['class' => $classes];
        if ($id !== null && $id !== '') {
            $slotAttrs['id'] = $id;
        }
        if ($ctx->isEditorMode()) {
            $slotAttrs['data-bky-slot-label'] = $slotLabel;
        }

        return '<div' . HtmlString::attrs($ctx->slotAttrs($node, $slotName, $slotAttrs)) . '>' . $slotHtml . '</div>';
    }

    private static function defaultCloseButton(string $overlayId): string
    {
        return '<button type="button" data-bky-action="overlay.close" data-bky-target="' . \esc_attr($overlayId) . '" data-bky-event="click" class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-surface-elevated text-text-base transition-colors hover:bg-surface-overlay" aria-label="Close overlay">×</button>';
    }

    private static function dialogActionsHtml(string $overlayId, string $cancelLabel, string $confirmLabel): string
    {
        return '<div class="flex flex-wrap justify-end gap-3">'
            . '<button type="button" data-bky-action="overlay.close" data-bky-target="' . \esc_attr($overlayId) . '" data-bky-event="click" class="inline-flex items-center justify-center rounded-button border border-border-base bg-surface-elevated px-4 py-2 text-sm font-medium text-text-base transition-colors hover:bg-surface-overlay">' . \esc_html($cancelLabel) . '</button>'
            . '<button type="button" data-bky-action="overlay.close" data-bky-target="' . \esc_attr($overlayId) . '" data-bky-event="click" class="inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-2 text-sm font-medium text-text-on-accent transition-colors hover:bg-accent-hover">' . \esc_html($confirmLabel) . '</button>'
            . '</div>';
    }

    private static function cookieActionsHtml(string $overlayId, string $acceptLabel, string $rejectLabel): string
    {
        return '<div class="flex flex-wrap justify-end gap-3">'
            . '<button type="button" data-bky-cookie-set="reject" data-bky-target="' . \esc_attr($overlayId) . '" class="inline-flex items-center justify-center rounded-button border border-border-base bg-surface-elevated px-4 py-2 text-sm font-medium text-text-base transition-colors hover:bg-surface-overlay">' . \esc_html($rejectLabel) . '</button>'
            . '<button type="button" data-bky-cookie-set="accept" data-bky-target="' . \esc_attr($overlayId) . '" class="inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-2 text-sm font-medium text-text-on-accent transition-colors hover:bg-accent-hover">' . \esc_html($acceptLabel) . '</button>'
            . '</div>';
    }

    private static function commandPaletteBodyHtml(string $overlayId, string $description): string
    {
        return '<div data-bky-command-palette class="space-y-4">'
            . '<input type="search" data-bky-command-search placeholder="Search actions..." class="min-h-11 w-full rounded-button border border-border-base bg-surface-elevated px-4 py-3 text-sm text-text-base outline-none focus:border-accent-base" />'
            . ($description !== '' ? '<p class="text-sm leading-6 text-text-muted">' . \esc_html($description) . '</p>' : '')
            . '<div class="space-y-2">'
            . '<button type="button" data-bky-command-item="Open documentation" data-bky-action="overlay.close" data-bky-target="' . \esc_attr($overlayId) . '" data-bky-event="click" class="flex w-full items-center justify-between rounded-lg border border-border-subtle bg-surface-elevated px-4 py-3 text-left hover:border-accent-base hover:bg-accent-subtle"><span>Open documentation</span><span class="text-xs uppercase tracking-[0.16em] text-text-muted">Docs</span></button>'
            . '<button type="button" data-bky-command-item="Jump to dashboard" data-bky-action="overlay.close" data-bky-target="' . \esc_attr($overlayId) . '" data-bky-event="click" class="flex w-full items-center justify-between rounded-lg border border-border-subtle bg-surface-elevated px-4 py-3 text-left hover:border-accent-base hover:bg-accent-subtle"><span>Jump to dashboard</span><span class="text-xs uppercase tracking-[0.16em] text-text-muted">UI</span></button>'
            . '<button type="button" data-bky-command-item="Open support" data-bky-action="overlay.close" data-bky-target="' . \esc_attr($overlayId) . '" data-bky-event="click" class="flex w-full items-center justify-between rounded-lg border border-border-subtle bg-surface-elevated px-4 py-3 text-left hover:border-accent-base hover:bg-accent-subtle"><span>Open support</span><span class="text-xs uppercase tracking-[0.16em] text-text-muted">Help</span></button>'
            . '</div></div>';
    }

    private static function ariaRole(string $variant): string
    {
        return match ($variant) {
            'tooltip' => 'tooltip',
            'dialog-confirm' => 'alertdialog',
            default => 'dialog',
        };
    }

    private static function blocksBackgroundInteraction(string $variant): bool
    {
        return in_array($variant, ['modal', 'offcanvas', 'drawer', 'dialog-confirm', 'popup', 'lightbox', 'command-palette'], true);
    }

    private static function defaultPlacement(string $variant): string
    {
        return match ($variant) {
            'offcanvas' => 'right',
            'drawer' => 'left',
            'popover' => 'bottom',
            'tooltip' => 'top',
            'cookie-banner' => 'bottom',
            'notification-toast' => 'right',
            'command-palette' => 'top',
            default => 'center',
        };
    }

    private static function defaultBackdrop(string $variant): string
    {
        return match ($variant) {
            'popup', 'lightbox' => 'dim',
            'command-palette' => 'blur',
            'modal', 'offcanvas', 'drawer', 'dialog-confirm' => 'dim',
            default => 'none',
        };
    }

    private static function editorRootClasses(string $variant): string
    {
        $justify = in_array($variant, ['offcanvas'], true) ? 'justify-end' : (in_array($variant, ['drawer'], true) ? 'justify-start' : 'justify-center');
        return trim('relative flex min-h-64 w-full items-stretch ' . $justify . ' rounded-xl border border-dashed border-border-base bg-surface-elevated p-4');
    }

    private static function publicRootClasses(string $variant, string $placement, string $backdrop): string
    {
        $backdropClass = match ($backdrop) {
            'dim' => 'bg-black/45',
            'blur' => 'bg-black/25 backdrop-blur-sm',
            default => '',
        };

        if (in_array($variant, ['popover', 'tooltip'], true)) {
            return 'fixed inset-0 z-50 flex items-start justify-center pointer-events-none';
        }

        if ($variant === 'notification-toast') {
            return 'fixed inset-0 z-50 flex items-end justify-end p-4 pointer-events-none';
        }

        if ($variant === 'cookie-banner') {
            return 'fixed inset-x-0 bottom-0 z-50 flex justify-center p-4';
        }

        if ($variant === 'command-palette') {
            return trim('fixed inset-0 z-50 flex items-start justify-center px-4 pt-6 ' . $backdropClass);
        }

        $placementClasses = match ($variant) {
            'offcanvas', 'drawer' => match ($placement) {
                'left' => 'justify-start items-stretch',
                'top' => 'justify-center items-start',
                'bottom' => 'justify-center items-end',
                default => 'justify-end items-stretch',
            },
            default => match ($placement) {
                'top' => 'justify-center items-start pt-8',
                'bottom' => 'justify-center items-end pb-8',
                'left' => 'justify-start items-center pl-8',
                'right' => 'justify-end items-center pr-8',
                default => 'justify-center items-center p-6',
            },
        };

        return trim('fixed inset-0 z-50 flex ' . $placementClasses . ' ' . $backdropClass);
    }

    private static function panelClasses(string $variant, string $placement, string $size, bool $editorMode): string
    {
        $base = 'pointer-events-auto relative overflow-hidden border border-border-base bg-surface-base text-text-base shadow-xl';
        if (in_array($variant, ['tooltip'], true)) {
            $base .= ' rounded-lg px-4 py-3';
        } elseif ($variant === 'notification-toast') {
            return 'pointer-events-auto relative w-full max-w-sm rounded-xl border border-border-base bg-surface-base text-text-base shadow-xl';
        } elseif ($variant === 'cookie-banner') {
            return 'pointer-events-auto relative w-full max-w-4xl rounded-2xl border border-border-base bg-surface-base text-text-base shadow-xl';
        } elseif ($variant === 'command-palette') {
            return 'pointer-events-auto relative w-full max-w-2xl rounded-2xl border border-border-base bg-surface-base text-text-base shadow-2xl';
        } elseif (in_array($variant, ['offcanvas', 'drawer'], true)) {
            $base .= ' h-full';
        } else {
            $base .= ' rounded-xl';
        }

        if ($editorMode) {
            return trim($base . ' w-full max-w-2xl');
        }

        if (in_array($variant, ['offcanvas', 'drawer'], true)) {
            $sizeClasses = in_array($placement, ['top', 'bottom'], true)
                ? match ($size) {
                    'sm' => 'w-full h-64',
                    'lg' => 'w-full h-96',
                    'xl' => 'w-full h-screen',
                    'full' => 'w-full h-screen rounded-none',
                    default => 'w-full h-80',
                }
                : match ($size) {
                    'sm' => 'w-full max-w-sm',
                    'lg' => 'w-full max-w-lg',
                    'xl' => 'w-full max-w-xl',
                    'full' => 'w-full max-w-none rounded-none',
                    default => 'w-full max-w-md',
                };

            if ($variant === 'drawer') {
                $sizeClasses .= ' rounded-none';
            }

            return trim($base . ' ' . $sizeClasses);
        }

        $sizeClasses = match ($size) {
            'sm' => 'w-full max-w-sm',
            'lg' => 'w-full max-w-lg',
            'xl' => 'w-full max-w-xl',
            'full' => 'w-full max-w-5xl',
            default => 'w-full max-w-md',
        };

        return trim($base . ' ' . $sizeClasses);
    }
}