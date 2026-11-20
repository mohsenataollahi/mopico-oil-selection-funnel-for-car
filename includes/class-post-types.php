<?php

declare(strict_types=1);

namespace Mopico\OilFunnel;

if (! defined('ABSPATH')) {
    exit;
}

final class PostTypes
{
    public const GROUP = 'mopico_oil_group';

    public const BRAND = 'mopico_oil_brand';

    public const MODEL = 'mopico_oil_model';

    public const VARIANT = 'mopico_oil_variant';

    public const BRAND_GROUP_META = '_mopico_oil_group_id';

    public const MODEL_BRAND_META = '_mopico_oil_brand_id';

    public const VARIANT_MODEL_META = '_mopico_oil_model_id';

    public const SPECS_META = '_mopico_oil_specs';

    public const RECOMMENDATIONS_META = '_mopico_oil_recommendations';

    public static function register(): void
    {
        self::registerType(self::GROUP, self::labels('گروه', 'گروه‌ها', 'انتخاب روغن'), true);
        self::registerType(self::BRAND, self::labels('سازنده', 'سازندگان', 'سازندگان'), false);
        self::registerType(self::MODEL, self::labels('مدل', 'مدل‌ها', 'مدل‌ها'), false);
        self::registerType(self::VARIANT, self::labels('موتور', 'موتورها', 'موتورها'), false);
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return [self::GROUP, self::BRAND, self::MODEL, self::VARIANT];
    }

    /**
     * @param list<string> $statuses
     * @return list<\WP_Post>
     */
    public static function find(string $type, array $statuses): array
    {
        $posts = get_posts([
            'post_type' => $type,
            'post_status' => $statuses,
            'posts_per_page' => -1,
            'orderby' => [
                'menu_order' => 'ASC',
                'title' => 'ASC',
            ],
            'no_found_rows' => true,
            'suppress_filters' => false,
        ]);

        $found = [];
        foreach ($posts as $post) {
            if ($post instanceof \WP_Post) {
                $found[] = $post;
            }
        }

        if ($found !== []) {
            update_meta_cache('post', wp_list_pluck($found, 'ID'));
            self::primeThumbnails($found);
        }

        return $found;
    }

    /**
     * @param list<\WP_Post> $posts
     */
    private static function primeThumbnails(array $posts): void
    {
        $thumbIds = [];
        foreach ($posts as $post) {
            $thumbId = (int) get_post_thumbnail_id($post);
            if ($thumbId > 0) {
                $thumbIds[$thumbId] = $thumbId;
            }
        }
        if ($thumbIds === []) {
            return;
        }

        _prime_post_caches(array_values($thumbIds), false, true);
    }

    /**
     * @param array<string, string> $labels
     */
    private static function registerType(string $slug, array $labels, bool $isRoot): void
    {
        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => $isRoot ? true : 'edit.php?post_type=' . self::GROUP,
            'supports' => ['title', 'thumbnail', 'page-attributes'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
            'exclude_from_search' => true,
            'show_in_rest' => false,
        ];
        if ($isRoot) {
            $args['menu_icon'] = 'dashicons-car';
            $args['menu_position'] = 26;
        }

        register_post_type($slug, $args);
    }

    /**
     * @return array<string, string>
     */
    private static function labels(string $singular, string $plural, string $menu): array
    {
        return [
            'name' => $plural,
            'singular_name' => $singular,
            'add_new' => 'افزودن',
            'add_new_item' => 'افزودن ' . $singular,
            'edit_item' => 'ویرایش ' . $singular,
            'new_item' => $singular . ' تازه',
            'search_items' => 'جستجوی ' . $plural,
            'not_found' => $plural . ' پیدا نشد.',
            'not_found_in_trash' => 'در زباله‌دان چیزی نیست.',
            'all_items' => $plural,
            'menu_name' => $menu,
        ];
    }
}
