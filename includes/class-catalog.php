<?php

declare(strict_types=1);

namespace Mopico\OilFunnel;

if (! defined('ABSPATH')) {
    exit;
}

final class Catalog
{
    public const CACHE_KEY = 'mopico_oil_catalog';

    private const CACHE_TTL = HOUR_IN_SECONDS;

    /** @var array<string, mixed>|null */
    private static $data = null;

    /**
     * @return array{
     *     groups: list<array{id: int, title: string, image: string}>,
     *     brands: list<array{id: int, title: string, image: string, groupId: int}>,
     *     models: list<array{id: int, title: string, image: string, brandId: int}>,
     *     variants: list<array<string, mixed>>,
     *     ui: array<string, mixed>
     * }
     */
    public static function tree(): array
    {
        $tree = self::data();
        $tree['ui'] = self::ui();

        return $tree;
    }

    /**
     * @param mixed $post
     */
    public static function forgetSavedPost(int $postId, $post = null): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        $type = $post instanceof \WP_Post ? $post->post_type : get_post_type($postId);
        if (! is_string($type)) {
            return;
        }
        if (in_array($type, PostTypes::slugs(), true) || $type === SiteProducts::postType()) {
            self::forget();
        }
    }

    public static function forgetOption(string $option): void
    {
        $watched = [
            SiteProducts::OPTION_POST_TYPE,
            SiteProducts::OPTION_TAXONOMY,
            SiteProducts::OPTION_LINE_META,
        ];
        if (in_array($option, $watched, true)) {
            self::forget();
        }
    }

    public static function forget(): void
    {
        self::$data = null;
        delete_transient(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private static function data(): array
    {
        if (is_array(self::$data)) {
            return self::$data;
        }

        $cached = get_transient(self::CACHE_KEY);
        if (self::isData($cached)) {
            self::$data = $cached;

            return self::$data;
        }

        self::$data = self::build();
        set_transient(self::CACHE_KEY, self::$data, self::CACHE_TTL);

        return self::$data;
    }

    /**
     * @param mixed $value
     */
    private static function isData($value): bool
    {
        if (! is_array($value)) {
            return false;
        }
        foreach (['groups', 'brands', 'models', 'variants'] as $key) {
            if (! isset($value[$key]) || ! is_array($value[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private static function build(): array
    {
        $groups = self::nodes(PostTypes::GROUP);
        $brands = self::children(
            PostTypes::BRAND,
            PostTypes::BRAND_GROUP_META,
            'groupId',
            self::idSet($groups)
        );
        $models = self::children(
            PostTypes::MODEL,
            PostTypes::MODEL_BRAND_META,
            'brandId',
            self::idSet($brands)
        );

        return [
            'groups' => $groups,
            'brands' => $brands,
            'models' => $models,
            'variants' => self::variants(self::idSet($models)),
        ];
    }

    /**
     * @return list<array{id: int, title: string, image: string}>
     */
    private static function nodes(string $type): array
    {
        $nodes = [];
        foreach (PostTypes::find($type, ['publish']) as $post) {
            $nodes[] = self::node($post);
        }

        return $nodes;
    }

    /**
     * @param array<int, true> $parentIds
     * @return list<array{id: int, title: string, image: string, groupId?: int, brandId?: int}>
     */
    private static function children(string $type, string $metaKey, string $parentField, array $parentIds): array
    {
        $nodes = [];
        foreach (PostTypes::find($type, ['publish']) as $post) {
            $parentId = absint(get_post_meta($post->ID, $metaKey, true));
            if (! isset($parentIds[$parentId])) {
                continue;
            }
            $node = self::node($post);
            $node[$parentField] = $parentId;
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @param array<int, true> $modelIds
     * @return list<array<string, mixed>>
     */
    private static function variants(array $modelIds): array
    {
        $rows = [];
        $productIds = [];
        foreach (PostTypes::find(PostTypes::VARIANT, ['publish']) as $post) {
            $modelId = absint(get_post_meta($post->ID, PostTypes::VARIANT_MODEL_META, true));
            if (! isset($modelIds[$modelId])) {
                continue;
            }

            $stored = get_post_meta($post->ID, PostTypes::RECOMMENDATIONS_META, true);
            $recommendations = Fluids::readStored(is_array($stored) ? $stored : []);
            foreach ($recommendations as $recommendation) {
                foreach ($recommendation['products'] as $productId) {
                    $productIds[] = $productId;
                }
            }

            $specs = get_post_meta($post->ID, PostTypes::SPECS_META, true);
            $rows[] = [
                'post' => $post,
                'modelId' => $modelId,
                'recommendations' => $recommendations,
                'specs' => is_array($specs) ? $specs : [],
            ];
        }

        $cards = SiteProducts::cards($productIds);
        $variants = [];
        foreach ($rows as $row) {
            $post = $row['post'];
            $variants[] = [
                'id' => (int) $post->ID,
                'title' => $post->post_title,
                'image' => self::imageUrl((int) $post->ID),
                'modelId' => $row['modelId'],
                'specs' => self::publicSpecs($row['specs']),
                'fluids' => self::publicFluids($row['recommendations'], $cards),
            ];
        }

        return $variants;
    }

    /**
     * @param array<string, mixed> $stored
     * @return list<array{label: string, value: string}>
     */
    private static function publicSpecs(array $stored): array
    {
        $specs = [];
        foreach (Fluids::readSpecs($stored) as $key => $value) {
            if ($value === '') {
                continue;
            }
            $specs[] = [
                'label' => Fluids::specs()[$key],
                'value' => $value,
            ];
        }

        return $specs;
    }

    /**
     * @param array<string, array{volume: string, products: list<int>}> $recommendations
     * @param array<int, array{id: int, title: string, subtitle: string, url: string, image: string}> $cards
     * @return list<array{key: string, label: string, volume: string, products: list<array<string, mixed>>}>
     */
    private static function publicFluids(array $recommendations, array $cards): array
    {
        $fluids = [];
        foreach (Fluids::all() as $key => $label) {
            $products = [];
            foreach ($recommendations[$key]['products'] as $productId) {
                if (isset($cards[$productId])) {
                    $products[] = $cards[$productId];
                }
            }
            $storedIds = $recommendations[$key]['products'];
            if ($products === [] && $storedIds === [] && $recommendations[$key]['volume'] === '') {
                continue;
            }
            $fluids[] = [
                'key' => $key,
                'label' => $label,
                'volume' => $recommendations[$key]['volume'],
                'products' => $products,
            ];
        }

        return $fluids;
    }

    /**
     * @param list<array{id: int}> $nodes
     * @return array<int, true>
     */
    private static function idSet(array $nodes): array
    {
        $set = [];
        foreach ($nodes as $node) {
            $set[$node['id']] = true;
        }

        return $set;
    }

    /**
     * @return array{id: int, title: string, image: string}
     */
    private static function node(\WP_Post $post): array
    {
        return [
            'id' => (int) $post->ID,
            'title' => $post->post_title,
            'image' => self::imageUrl((int) $post->ID),
        ];
    }

    private static function imageUrl(int $postId): string
    {
        $url = get_the_post_thumbnail_url($postId, 'medium');
        if (! is_string($url) || $url === '') {
            return '';
        }

        return esc_url_raw($url);
    }

    /**
     * @return array<string, mixed>
     */
    private static function ui(): array
    {
        return [
            'steps' => ['انتخاب گروه', 'انتخاب سازنده', 'انتخاب مدل', 'انتخاب موتور', 'نتایج'],
            'next' => 'مرحله بعد',
            'back' => 'مرحله قبل',
            'emptyStep' => 'موردی برای این مرحله ثبت نشده.',
            'emptyProducts' => 'برای این موتور محصولی انتخاب نشده.',
            'missingVariant' => 'این موتور در فهرست منتشرشده پیدا نشد.',
            'volume' => 'حجم سیال مورد نیاز (لیتر)',
            'pick' => 'یک مورد را انتخاب کنید.',
        ];
    }
}
