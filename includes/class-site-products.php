<?php

declare(strict_types=1);

namespace Mopico\OilFunnel;

if (! defined('ABSPATH')) {
    exit;
}

final class SiteProducts
{
    public const OPTION_POST_TYPE = 'mopico_oil_product_post_type';

    public const OPTION_TAXONOMY = 'mopico_oil_product_taxonomy';

    public const OPTION_LINE_META = 'mopico_oil_product_line_meta';

    public static function postType(): string
    {
        return self::textOption(self::OPTION_POST_TYPE, 'product', 'mopico_oil_product_post_type');
    }

    public static function taxonomy(): string
    {
        return self::textOption(self::OPTION_TAXONOMY, 'group', 'mopico_oil_product_taxonomy', true);
    }

    public static function lineMeta(): string
    {
        return self::textOption(self::OPTION_LINE_META, 'red-title', 'mopico_oil_product_line_meta', true);
    }

    /**
     * @param mixed $value
     */
    public static function sanitizePostType($value): string
    {
        $type = is_string($value) ? sanitize_key($value) : '';
        if ($type !== '' && post_type_exists($type) && ! in_array($type, PostTypes::slugs(), true)) {
            return $type;
        }

        $current = get_option(self::OPTION_POST_TYPE, 'product');

        return is_string($current) && $current !== '' ? sanitize_key($current) : 'product';
    }

    /**
     * @param mixed $value
     */
    public static function sanitizeTaxonomy($value): string
    {
        $taxonomy = is_string($value) ? sanitize_key($value) : '';
        $postType = self::postedPostType();
        if ($taxonomy === '' || self::taxonomyMatchesPostType($taxonomy, $postType)) {
            return $taxonomy;
        }

        $current = get_option(self::OPTION_TAXONOMY, 'group');
        $current = is_string($current) ? sanitize_key($current) : '';
        if ($current !== '' && self::taxonomyMatchesPostType($current, $postType)) {
            return $current;
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    public static function sanitizeLineMeta($value): string
    {
        $meta = is_string($value) ? sanitize_key($value) : '';
        if (strlen($meta) > 191) {
            $meta = substr($meta, 0, 191);
        }

        return $meta;
    }

    private static function postedPostType(): string
    {
        $raw = $_POST[self::OPTION_POST_TYPE] ?? '';
        if (is_string($raw)) {
            $type = sanitize_key(wp_unslash($raw));
            if ($type !== '' && post_type_exists($type) && ! in_array($type, PostTypes::slugs(), true)) {
                return $type;
            }
        }

        return self::postType();
    }

    private static function taxonomyMatchesPostType(string $taxonomy, string $postType): bool
    {
        return $taxonomy !== ''
            && taxonomy_exists($taxonomy)
            && post_type_exists($postType)
            && is_object_in_taxonomy($postType, $taxonomy);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public static function choices(): array
    {
        $postType = self::postType();
        if (! post_type_exists($postType)) {
            return [];
        }

        $posts = get_posts([
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
            'suppress_filters' => false,
        ]);

        $found = [];
        foreach ($posts as $post) {
            if ($post instanceof \WP_Post) {
                $found[] = $post;
            }
        }

        if ($found === []) {
            return [];
        }

        $taxonomy = self::taxonomy();
        if ($taxonomy !== '' && taxonomy_exists($taxonomy)) {
            update_object_term_cache(wp_list_pluck($found, 'ID'), $postType);
        }

        $choices = [];
        foreach ($found as $post) {
            $choices[] = [
                'id' => (int) $post->ID,
                'label' => self::adminLabel($post),
            ];
        }

        return $choices;
    }

    /**
     * @return array<int, true>
     */
    public static function publishedIds(): array
    {
        $ids = [];
        foreach (self::choices() as $choice) {
            $ids[$choice['id']] = true;
        }

        return $ids;
    }

    /**
     * @param list<int> $postIds
     * @return array<int, array{id: int, title: string, subtitle: string, url: string, image: string}>
     */
    public static function cards(array $postIds): array
    {
        $postType = self::postType();
        if (! post_type_exists($postType)) {
            return [];
        }

        $ids = [];
        foreach ($postIds as $postId) {
            $postId = absint($postId);
            if ($postId > 0) {
                $ids[$postId] = $postId;
            }
        }
        if ($ids === []) {
            return [];
        }

        $posts = get_posts([
            'post_type' => $postType,
            'post_status' => 'publish',
            'post__in' => array_values($ids),
            'posts_per_page' => count($ids),
            'orderby' => 'post__in',
            'no_found_rows' => true,
            'update_post_meta_cache' => true,
            'suppress_filters' => false,
        ]);

        $loaded = [];
        foreach ($posts as $post) {
            if ($post instanceof \WP_Post) {
                $loaded[] = $post;
            }
        }
        if ($loaded !== []) {
            update_meta_cache('post', wp_list_pluck($loaded, 'ID'));
        }

        $thumbIds = [];
        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }
            $thumbId = (int) get_post_thumbnail_id($post);
            if ($thumbId > 0) {
                $thumbIds[] = $thumbId;
            }
        }
        if ($thumbIds !== []) {
            _prime_post_caches($thumbIds, false, true);
        }

        $cards = [];
        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }
            $card = self::cardFromPost($post);
            if ($card !== null) {
                $cards[(int) $post->ID] = $card;
            }
        }

        return $cards;
    }

    /**
     * @return array{id: int, title: string, subtitle: string, url: string, image: string}|null
     */
    private static function cardFromPost(\WP_Post $post): ?array
    {
        $url = self::publicUrl(get_permalink($post));
        if ($url === '') {
            return null;
        }

        return [
            'id' => (int) $post->ID,
            'title' => $post->post_title,
            'subtitle' => self::line((int) $post->ID),
            'url' => $url,
            'image' => self::publicUrl(get_the_post_thumbnail_url($post, 'medium')),
        ];
    }

    private static function adminLabel(\WP_Post $post): string
    {
        $parts = [$post->post_title];
        $line = self::line((int) $post->ID);
        if ($line !== '') {
            $parts[] = $line;
        }

        $taxonomy = self::taxonomy();
        if ($taxonomy !== '' && taxonomy_exists($taxonomy)) {
            $terms = get_the_terms($post, $taxonomy);
            if (is_array($terms) && isset($terms[0]) && $terms[0] instanceof \WP_Term) {
                $parts[] = $terms[0]->name;
            }
        }

        return implode(' — ', $parts);
    }

    private static function line(int $postId): string
    {
        $metaKey = self::lineMeta();
        if ($metaKey === '') {
            return '';
        }

        $line = get_post_meta($postId, $metaKey, true);
        if (! is_string($line)) {
            return '';
        }

        $line = trim($line);
        if ($line === '' || $line === '-') {
            return '';
        }

        return $line;
    }

    private static function textOption(string $option, string $default, string $filter, bool $allowEmpty = false): string
    {
        $stored = get_option($option, $default);
        if (! is_string($stored) || ($stored === '' && ! $allowEmpty)) {
            $stored = $default;
        }

        $filtered = apply_filters($filter, $stored);
        if (! is_string($filtered) || ($filtered === '' && ! $allowEmpty)) {
            return $default;
        }

        return $filtered;
    }

    /**
     * @param mixed $url
     */
    private static function publicUrl($url): string
    {
        if (! is_string($url) || $url === '') {
            return '';
        }

        return esc_url_raw($url);
    }
}
