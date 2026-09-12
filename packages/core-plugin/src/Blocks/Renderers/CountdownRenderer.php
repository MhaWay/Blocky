<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

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
        if ($ctx->isEditorMode()) {
            return '';
        }

        return '<script>(function(){var root=document.currentScript.closest("[data-bky-countdown-root]");if(!root)return;var target=root.getAttribute("data-bky-target-date");if(!target)return;var nodes={days:root.querySelector("[data-bky-countdown-unit=days]"),hours:root.querySelector("[data-bky-countdown-unit=hours]"),minutes:root.querySelector("[data-bky-countdown-unit=minutes]"),seconds:root.querySelector("[data-bky-countdown-unit=seconds]")};var targetTime=Date.parse(target);if(Number.isNaN(targetTime))return;function draw(){var diff=Math.max(0,targetTime-Date.now());var days=Math.floor(diff/86400000);var hours=Math.floor(diff%86400000/3600000);var minutes=Math.floor(diff%3600000/60000);var seconds=Math.floor(diff%60000/1000);nodes.days.textContent=String(days).padStart(2,"0");nodes.hours.textContent=String(hours).padStart(2,"0");nodes.minutes.textContent=String(minutes).padStart(2,"0");nodes.seconds.textContent=String(seconds).padStart(2,"0");}draw();window.setInterval(draw,1000);})();</script>';
    }
}