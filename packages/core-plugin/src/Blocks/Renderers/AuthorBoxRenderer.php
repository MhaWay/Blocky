<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class AuthorBoxRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $postId = $ctx->currentPostId() > 0 ? $ctx->currentPostId() : self::resolveCurrentPostId();
        if ($postId <= 0) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Author details will render for the current post.' : '');
        }

        $post = get_post($postId);
        if (!$post instanceof \WP_Post) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), '');
        }

        $authorId = (int) $post->post_author;
        $name = get_the_author_meta('display_name', $authorId) ?: __('Unknown author', 'blocky');
        $bio = trim((string) get_the_author_meta('description', $authorId));
        $archiveUrl = get_author_posts_url($authorId);
        $title = trim((string) ($node->props['title'] ?? 'About the author'));
        $showAvatar = (bool) ($node->props['showAvatar'] ?? true);
        $showBio = (bool) ($node->props['showBio'] ?? true);
        $showArchiveLink = (bool) ($node->props['showArchiveLink'] ?? true);
        $classes = $ctx->resolveVariantClasses($node, [
            'avatarSize' => ['sm' => 'h-14 w-14', 'base' => 'h-20 w-20', 'lg' => 'h-24 w-24'],
            'layout' => ['row' => 'flex-row items-start', 'stacked' => 'flex-col items-start'],
            'padding' => ['none' => 'p-0', 'sm' => 'p-4', 'base' => 'p-6', 'lg' => 'p-8'],
            'background' => ['transparent' => '', 'surface' => 'bg-surface-base', 'elevated' => 'bg-surface-elevated'],
            'border' => ['none' => '', 'subtle' => 'border border-border-subtle', 'base' => 'border border-border-base'],
            'radius' => ['none' => 'rounded-none', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'xl' => 'rounded-xl'],
        ]);
        $avatarClass = $ctx->resolveVariantClasses($node, ['avatarSize' => ['sm' => 'h-14 w-14', 'base' => 'h-20 w-20', 'lg' => 'h-24 w-24']]);

        $html = ($title !== '' ? '<p class="text-xs font-semibold uppercase tracking-[0.18em] text-text-muted">' . esc_html($title) . '</p>' : '')
            . '<div class="mt-4 flex gap-4 ' . esc_attr($ctx->resolveVariantClasses($node, ['layout' => ['row' => 'flex-row items-start', 'stacked' => 'flex-col items-start']])) . '">'
            . ($showAvatar ? '<div class="overflow-hidden rounded-full ' . esc_attr($avatarClass) . '">' . get_avatar($authorId, 96, '', (string) $name, ['class' => 'h-full w-full object-cover']) . '</div>' : '')
            . '<div class="space-y-3"><h3 class="text-lg font-semibold text-text-base">' . esc_html((string) $name) . '</h3>'
            . ($showBio && $bio !== '' ? '<p class="text-sm leading-6 text-text-muted">' . esc_html($bio) . '</p>' : '')
            . ($showArchiveLink ? '<a href="' . esc_url($archiveUrl) . '" class="inline-flex text-sm font-medium text-accent-text hover:underline">' . esc_html__('View all posts', 'blocky') . '</a>' : '')
            . '</div></div>';

        return HtmlString::element('section', $ctx->blockAttrs($node, ['class' => trim($classes)]), $html);
    }

    private static function resolveCurrentPostId(): int
    {
        $postId = function_exists('get_the_ID') ? (int) get_the_ID() : 0;
        if ($postId > 0) {
            return $postId;
        }

        return function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
    }
}