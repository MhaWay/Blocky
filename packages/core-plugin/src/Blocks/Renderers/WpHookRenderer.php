<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class WpHookRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $hookName = (string) ($node->props['hookName'] ?? '');
        $type     = (string) ($node->props['type'] ?? 'action'); // 'action' | 'filter'

        if ($hookName === '') {
            if ($ctx->isEditorMode()) {
                return HtmlString::element(
                    'div',
                    $ctx->blockAttrs($node, ['style' => 'border:1px dashed #d1d5db;padding:12px;border-radius:6px;']),
                    '<span style="font-size:12px;color:#9ca3af;font-style:italic">⚡ WordPress Hook (enter hook name)</span>',
                );
            }
            return HtmlString::of('');
        }

        if ($ctx->isEditorMode()) {
            $label = \esc_html($type === 'filter' ? "apply_filters('{$hookName}')" : "do_action('{$hookName}')");
            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['style' => 'border:1px dashed #a78bfa;padding:8px 12px;border-radius:6px;background:rgba(139,92,246,.06);']),
                "<span style=\"font-size:12px;color:#7c3aed;font-family:monospace\">⚡ {$label}</span>",
            );
        }

        if ($type === 'filter') {
            $output = (string) \apply_filters($hookName, ''); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- hook names come from registered block descriptors, a closed set.
        } else {
            \ob_start();
            \do_action($hookName); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- hook names come from registered block descriptors, a closed set.
            $output = (string) \ob_get_clean();
        }

        if ($output === '') {
            return HtmlString::of('');
        }

        return HtmlString::element('div', $ctx->blockAttrs($node, []), $output);
    }
}
