<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class MarqueeRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $items = self::parseItems((string) ($node->props['items'] ?? ''));
        $speed = max(8, min(60, (int) ($node->props['speed'] ?? 20)));
        $pauseOnHover = (bool) ($node->props['pauseOnHover'] ?? true);

        $wrapperClasses = $ctx->resolveVariantClasses($node, [
            'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base', 'contrast' => 'bg-text-base text-text-on-accent'],
        ]);
        $trackClasses = $ctx->resolveVariantClasses($node, [
            'direction' => ['left' => '', 'right' => '[animation-direction:reverse]'],
        ]);

        if ($items === []) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Add marquee items to build the block.' : '');
        }

        $content = self::renderItems($items) . self::renderItems($items);
        $pauseAttr = $pauseOnHover ? 'group-hover:[animation-play-state:paused]' : '';

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['class' => trim('group overflow-hidden rounded-card px-4 py-3 ' . $wrapperClasses)]),
            '<div class="flex min-w-max gap-4 whitespace-nowrap [mask-image:linear-gradient(to_right,transparent,black_8%,black_92%,transparent)]">'
            . '<div class="flex min-w-max gap-4 [animation:bky-marquee_var(--bky-marquee-speed)_linear_infinite] ' . esc_attr(trim($trackClasses . ' ' . $pauseAttr)) . '" style="--bky-marquee-speed:' . $speed . 's">' . $content . '</div>'
            . '</div>'
            . '<style>@keyframes bky-marquee{from{transform:translate3d(0,0,0)}to{transform:translate3d(-50%,0,0)}}</style>'
        );
    }

    /** @return string[] */
    private static function parseItems(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
    }

    /** @param string[] $items */
    private static function renderItems(array $items): string
    {
        $html = '';
        foreach ($items as $item) {
            $html .= '<span class="inline-flex items-center gap-4 rounded-full border border-current/10 px-4 py-2 text-sm font-medium"><span class="h-2 w-2 rounded-full bg-accent-base"></span>' . esc_html($item) . '</span>';
        }
        return $html;
    }
}