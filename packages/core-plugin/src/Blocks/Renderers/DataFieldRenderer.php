<?php
/**
 * @package Blocky\Core\Blocks\Renderers
 */

declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Node;
use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Support\DataFields;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

/**
 * Renders a single WP data value (title, excerpt, featured image, price,
 * user bio, ...) for the current loop item, page or author context.
 */
final class DataFieldRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $key      = (string) ($node->props['field'] ?? 'title');
        $fallback = (string) ($node->props['fallback'] ?? '');
        $field    = DataFields::field($key);

        if ($field === null) {
            if (!$ctx->isEditorMode()) {
                return HtmlString::of('');
            }
            return HtmlString::of('<em style="opacity:.45">' . \esc_html($key) . '</em>');
        }

        $postId = (int) \get_the_ID();
        if ($postId <= 0) {
            $postId = \max(0, (int) \get_queried_object_id());
        }
        if ($postId <= 0) {
            $sample  = \get_posts(['numberposts' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'fields' => 'ids']);
            $postId  = $sample !== [] ? (int) $sample[0] : 0;
        }

        $user = $this->resolveUser($key, $postId);

        if ($field['type'] === 'image') {
            return $this->renderImage($ctx, $node, $key, $postId, $user, $fallback);
        }

        $value = $this->textValue($key, $postId, $user);
        if ($value === '') {
            return $this->placeholder($ctx, $field['label'], $fallback);
        }

        return HtmlString::element(
            'span',
            $ctx->blockAttrs($node, ['class' => 'block text-sm leading-6 text-text-muted']),
            \esc_html($value)
        );
    }

    private function placeholder(RenderContext $ctx, string $label, string $fallback): HtmlString
    {
        if ($fallback !== '') {
            return HtmlString::element('span', ['class' => 'block text-sm text-text-muted'], \esc_html($fallback));
        }
        if (!$ctx->isEditorMode()) {
            return HtmlString::of('');
        }
        return HtmlString::of('<em style="opacity:.45">' . \esc_html($label) . '</em>');
    }

    private function renderImage(RenderContext $ctx, Node $node, string $key, int $postId, ?\WP_User $user, string $fallback): HtmlString
    {
        $url = '';
        if ($key === 'featured_image' && $postId > 0) {
            $url = (string) \get_the_post_thumbnail_url($postId, 'medium_large');
        } elseif ($key === 'author_avatar' && $postId > 0) {
            $author = (int) \get_post_field('post_author', $postId);
            $url    = $author > 0 ? (string) \get_avatar_url($author, ['size' => 96]) : '';
        } elseif ($key === 'user_avatar' && $user !== null) {
            $url = (string) \get_avatar_url($user->ID, ['size' => 96]);
        }

        if ($url === '') {
            return $this->placeholder($ctx, 'Image', $fallback);
        }

        $imgClass = ($key === 'author_avatar' || $key === 'user_avatar')
            ? 'h-12 w-12 rounded-full object-cover'
            : 'h-44 w-full rounded-card object-cover';

        $img = HtmlString::void('img', [
            'src' => $url,
            'alt' => '',
            'loading' => 'lazy',
            'class' => $imgClass,
        ]);

        return HtmlString::element('div', $ctx->blockAttrs($node), $img->toString());
    }

    private function resolveUser(string $key, int $postId): ?\WP_User
    {
        if (!\str_starts_with($key, 'user_')) {
            return null;
        }

        $queried = \get_queried_object();
        if ($queried instanceof \WP_User) {
            return $queried;
        }

        if ($postId > 0) {
            $author = \get_userdata((int) \get_post_field('post_author', $postId));
            if ($author instanceof \WP_User) {
                return $author;
            }
        }

        $users = \get_users(['number' => 1, 'count_total' => false, 'has_published_posts' => true]);
        return $users !== [] ? $users[0] : null;
    }

    private function textValue(string $key, int $postId, ?\WP_User $user): string
    {
        switch ($key) {
            case 'user_name':
                return $user?->display_name ?? '';
            case 'user_bio':
                return $user !== null ? (string) \get_user_meta($user->ID, 'description', true) : '';
            case 'user_registered':
                if ($user === null) {
                    return '';
                }
                return \date_i18n('F Y', \strtotime((string) $user->user_registered) ?: \time());
            case 'title':
                return $postId > 0 ? (string) \get_the_title($postId) : '';
            case 'excerpt':
                return $postId > 0 ? \wp_strip_all_tags((string) \get_the_excerpt($postId)) : '';
            case 'content':
                if ($postId <= 0) {
                    return '';
                }
                $raw = \get_post_field('post_content', $postId);
                return \wp_html_excerpt(\wp_strip_all_tags(\is_string($raw) ? $raw : ''), 280);
            case 'permalink':
                return $postId > 0 ? (string) \get_permalink($postId) : '';
            case 'date':
                return $postId > 0 ? (string) \get_the_date('', $postId) : '';
            case 'modified':
                return $postId > 0 ? (string) \get_the_modified_date('', $postId) : '';
            case 'author_name':
                if ($postId <= 0) {
                    return '';
                }
                return (string) \get_the_author_meta('display_name', (int) \get_post_field('post_author', $postId));
            case 'comment_count':
                return $postId > 0 ? (string) (int) \get_comments_number($postId) : '';
            case 'categories':
                if ($postId <= 0) {
                    return '';
                }
                $terms = \get_the_terms($postId, 'category');
                if (!\is_array($terms)) {
                    return '';
                }
                return \implode(', ', \array_map(static fn (\WP_Term $t): string => $t->name, $terms));
            case 'price':
                if ($postId <= 0 || !\function_exists('wc_get_product')) {
                    return '';
                }
                $product = \wc_get_product($postId);
                return $product !== null ? \wp_strip_all_tags((string) $product->get_price_html()) : '';
            case 'product_categories':
                if ($postId <= 0 || !\function_exists('wc_get_product_terms')) {
                    return '';
                }
                return \implode(', ', \array_map('strval', (array) \wc_get_product_terms($postId, 'product_cat', ['fields' => 'names'])));
            default:
                return '';
        }
    }
}
