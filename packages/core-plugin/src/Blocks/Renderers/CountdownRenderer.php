<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class CountdownRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $targetDate = trim((string) ($node->props['targetDate'] ?? ''));
        $showLabels = (bool) ($node->props['showLabels'] ?? true);
        $layoutClasses = $ctx->resolveVariantClasses($node, [
            'layout' => ['grid' => 'grid grid-cols-2 gap-4 md:grid-cols-4', 'inline' => 'flex flex-wrap gap-4'],
            'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base', 'contrast' => 'bg-text-base text-text-on-accent'],
        ]);

        [$days, $hours, $minutes, $seconds] = self::remainingUnits($targetDate);
        $units = [
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
        ];

        $items = '';
        foreach ($units as $key => $value) {
            $items .= '<div class="rounded-card p-4 text-center ' . \esc_attr($layoutClasses) . '">'
                . '<div data-bky-countdown-unit="' . \esc_attr($key) . '" class="text-3xl font-semibold leading-none">' . \esc_html(str_pad((string) $value, 2, '0', STR_PAD_LEFT)) . '</div>'
                . ($showLabels ? '<div class="mt-2 text-xs font-semibold uppercase tracking-[0.18em] opacity-70">' . \esc_html($key) . '</div>' : '')
                . '</div>';
        }

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['data-bky-countdown-root' => 'bky-countdown-' . $node->id, 'data-bky-target-date' => $targetDate, 'class' => trim($layoutClasses)]),
            $items . self::script($ctx)
        );
    }

    /** @return array{int,int,int,int} */
    private static function remainingUnits(string $targetDate): array
    {
        if ($targetDate === '') {
            return [0, 0, 0, 0];
        }

        try {
            $target = new \DateTimeImmutable($targetDate);
            $now = new \DateTimeImmutable('now', $target->getTimezone());
            $diff = max(0, $target->getTimestamp() - $now->getTimestamp());
        } catch (\Throwable) {
            return [0, 0, 0, 0];
        }

        $days = intdiv($diff, 86400);
        $hours = intdiv($diff % 86400, 3600);
        $minutes = intdiv($diff % 3600, 60);
        $seconds = $diff % 60;
        return [$days, $hours, $minutes, $seconds];
    }

    private static function script(RenderContext $ctx): string
    {
        return '';
    }
}