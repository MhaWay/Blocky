<?php
/**
 * @package BlockyCoreSupport
 */

declare(strict_types=1);

namespace Blocky\Core\Support;

/**
 * Extensible closed registry of the WP data a component or dynamic block
 * can bind to. Keys are stable; renderers resolve them per loop item.
 */
final class DataFields
{
    /**
     * @return array<string, array{label: string, type: string}>
     */
    public static function all(): array
    {
        $fields = [
            'title'           => ['label' => 'Title', 'type' => 'text'],
            'excerpt'         => ['label' => 'Excerpt', 'type' => 'text'],
            'content'         => ['label' => 'Content', 'type' => 'text'],
            'featured_image'  => ['label' => 'Featured image', 'type' => 'image'],
            'permalink'       => ['label' => 'URL', 'type' => 'text'],
            'date'            => ['label' => 'Date', 'type' => 'text'],
            'modified'        => ['label' => 'Last updated', 'type' => 'text'],
            'author_name'     => ['label' => 'Author name', 'type' => 'text'],
            'author_avatar'   => ['label' => 'Author avatar', 'type' => 'image'],
            'comment_count'   => ['label' => 'Comment count', 'type' => 'text'],
            'categories'      => ['label' => 'Categories', 'type' => 'text'],
            'user_name'       => ['label' => 'User name', 'type' => 'text'],
            'user_bio'        => ['label' => 'User bio', 'type' => 'text'],
            'user_avatar'     => ['label' => 'User avatar', 'type' => 'image'],
            'user_registered' => ['label' => 'Member since', 'type' => 'text'],
        ];

        if (class_exists('WooCommerce')) {
            $fields['price']              = ['label' => 'Price', 'type' => 'text'];
            $fields['product_categories'] = ['label' => 'Product categories', 'type' => 'text'];
        }

        /**
         * Third-party sources (memberships, ACF, storefronts) register
         * their fields here and become available in every component.
         *
         * @param array<string, array{label: string, type: string}> $fields
         */
        return (array) apply_filters('blocky/data_fields', $fields);
    }

    /**
     * @return array{label: string, type: string}|null
     */
    public static function field(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
