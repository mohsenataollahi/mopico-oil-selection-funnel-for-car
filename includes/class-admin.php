<?php

declare(strict_types=1);

namespace Mopico\OilFunnel;

if (! defined('ABSPATH')) {
    exit;
}

final class Admin
{
    private static bool $missingParent = false;

    public static function boot(): void
    {
        add_action('add_meta_boxes', [self::class, 'addMetaBoxes']);
        add_action('save_post_' . PostTypes::BRAND, [self::class, 'saveBrand']);
        add_action('save_post_' . PostTypes::MODEL, [self::class, 'saveModel']);
        add_action('save_post_' . PostTypes::VARIANT, [self::class, 'saveVariant']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
        add_action('admin_notices', [self::class, 'shortcodeNotice']);
        add_action('admin_init', [self::class, 'registerProductSettings']);
        add_action('admin_menu', [self::class, 'addProductSettingsPage']);

        foreach (self::parents() as $type => $config) {
            add_filter(
                'manage_' . $type . '_posts_columns',
                static function (array $columns) use ($config): array {
                    return self::insertParentColumn($columns, $config['column']);
                }
            );
            add_action(
                'manage_' . $type . '_posts_custom_column',
                static function ($column, $postId) use ($config): void {
                    self::renderParentColumn((string) $column, (int) $postId, $config['meta']);
                },
                10,
                2
            );
        }
    }

    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'mopico-oil-brand-parent',
            'گروه',
            [self::class, 'renderBrandBox'],
            PostTypes::BRAND,
            'side',
            'high'
        );
        add_meta_box(
            'mopico-oil-model-parent',
            'سازنده',
            [self::class, 'renderModelBox'],
            PostTypes::MODEL,
            'side',
            'high'
        );
        add_meta_box(
            'mopico-oil-variant',
            'مدل، مشخصات و محصولات نتیجه',
            [self::class, 'renderVariantBox'],
            PostTypes::VARIANT,
            'normal',
            'high'
        );
    }

    public static function renderBrandBox(\WP_Post $post): void
    {
        self::parentFields($post, PostTypes::BRAND_GROUP_META, PostTypes::GROUP, 'گروه');
    }

    public static function renderModelBox(\WP_Post $post): void
    {
        self::parentFields($post, PostTypes::MODEL_BRAND_META, PostTypes::BRAND, 'سازنده');
    }

    public static function renderVariantBox(\WP_Post $post): void
    {
        wp_nonce_field('mopico_oil_variant_' . $post->ID, 'mopico_oil_variant_nonce');
        echo '<p>محصولاتی که اینجا تیک می‌خورند، در مرحله نتیجه فانل با تصویر و لینک خود محصول نشان داده می‌شوند.</p>';
        self::parentSelect($post, PostTypes::VARIANT_MODEL_META, PostTypes::MODEL, 'مدل');
        self::renderSpecs($post);
        self::renderFluids($post);
    }

    public static function saveBrand(int $postId): void
    {
        self::saveParent($postId, PostTypes::BRAND, PostTypes::BRAND_GROUP_META, PostTypes::GROUP);
    }

    public static function saveModel(int $postId): void
    {
        self::saveParent($postId, PostTypes::MODEL, PostTypes::MODEL_BRAND_META, PostTypes::BRAND);
    }

    public static function saveVariant(int $postId): void
    {
        if (! self::canSave($postId, 'mopico_oil_variant_nonce', 'mopico_oil_variant_' . $postId)) {
            return;
        }

        $parentId = self::postedParentId(PostTypes::MODEL);
        self::rememberMissingParent($parentId);
        update_post_meta($postId, PostTypes::VARIANT_MODEL_META, $parentId);
        update_post_meta($postId, PostTypes::SPECS_META, Fluids::sanitizeSpecs(self::postedArray('mopico_oil_specs')));
        update_post_meta(
            $postId,
            PostTypes::RECOMMENDATIONS_META,
            Fluids::sanitizeRecommendations(
                self::postedArray('mopico_oil_volume'),
                self::postedArray('mopico_oil_products'),
                SiteProducts::publishedIds()
            )
        );
    }

    public static function enqueue(string $hook): void
    {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        $screen = get_current_screen();
        if ($screen === null || $screen->post_type !== PostTypes::VARIANT) {
            return;
        }

        wp_enqueue_style(
            'mopico-oil-admin',
            MOPICO_OIL_URL . 'assets/admin.css',
            [],
            MOPICO_OIL_VERSION
        );
    }

    public static function shortcodeNotice(): void
    {
        $screen = get_current_screen();
        if ($screen === null || ! in_array($screen->post_type, PostTypes::slugs(), true)) {
            return;
        }

        $flag = $_GET['mopico_oil_missing_parent'] ?? '';
        $missingParent = is_string($flag) && sanitize_text_field(wp_unslash($flag)) === '1';
        if ($missingParent && isset(self::parents()[$screen->post_type])) {
            echo '<div class="notice notice-error"><p>والد انتخاب نشده. تا وقتی گروه، سازنده یا مدل را انتخاب نکنید، این مورد در فانل دیده نمی‌شود.</p></div>';
        }

        echo '<div class="notice notice-info"><p>شورت‌کد فانل: <code>[mopico_oil_funnel]</code></p></div>';
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    private static function insertParentColumn(array $columns, string $label): array
    {
        $next = [];
        foreach ($columns as $key => $columnLabel) {
            $next[$key] = $columnLabel;
            if ($key === 'title') {
                $next['mopico_oil_parent'] = $label;
            }
        }

        return $next;
    }

    private static function renderParentColumn(string $column, int $postId, string $metaKey): void
    {
        if ($column !== 'mopico_oil_parent') {
            return;
        }

        $parentId = absint(get_post_meta($postId, $metaKey, true));
        if ($parentId <= 0) {
            echo '—';
            return;
        }

        echo esc_html(get_the_title($parentId));
    }

    private static function parentFields(\WP_Post $post, string $metaKey, string $parentType, string $label): void
    {
        wp_nonce_field('mopico_oil_parent_' . $post->ID, 'mopico_oil_parent_nonce');
        self::parentSelect($post, $metaKey, $parentType, $label);
    }

    private static function parentSelect(\WP_Post $post, string $metaKey, string $parentType, string $label): void
    {
        $selected = absint(get_post_meta($post->ID, $metaKey, true));
        $parents = PostTypes::find($parentType, ['publish', 'draft', 'pending']);

        echo '<p><label for="mopico-oil-parent"><strong>' . esc_html($label) . '</strong></label></p>';
        if ($parents === []) {
            echo '<p>اول ' . esc_html($label) . ' را بسازید.</p>';
            return;
        }

        echo '<select id="mopico-oil-parent" name="mopico_oil_parent" class="widefat">';
        echo '<option value="0">انتخاب کنید</option>';
        foreach ($parents as $parent) {
            echo '<option value="' . esc_attr((string) $parent->ID) . '" ' . selected($selected, (int) $parent->ID, false) . '>';
            echo esc_html($parent->post_title);
            if ($parent->post_status !== 'publish') {
                echo ' (' . esc_html($parent->post_status) . ')';
            }
            echo '</option>';
        }
        echo '</select>';
    }

    private static function renderSpecs(\WP_Post $post): void
    {
        $stored = get_post_meta($post->ID, PostTypes::SPECS_META, true);
        $specs = Fluids::readSpecs(is_array($stored) ? $stored : []);

        echo '<h3>مشخصات موتور</h3>';
        foreach (Fluids::specs() as $key => $label) {
            echo '<p><label for="mopico-oil-spec-' . esc_attr($key) . '">' . esc_html($label) . '</label><br>';
            echo '<input class="widefat" type="text" id="mopico-oil-spec-' . esc_attr($key) . '" name="mopico_oil_specs[' . esc_attr($key) . ']" value="' . esc_attr($specs[$key]) . '"></p>';
        }
    }

    private static function renderFluids(\WP_Post $post): void
    {
        $stored = get_post_meta($post->ID, PostTypes::RECOMMENDATIONS_META, true);
        $recommendations = Fluids::readStored(is_array($stored) ? $stored : []);
        $choices = SiteProducts::choices();

        echo '<h3>محصولات هر سیال</h3>';
        if ($choices === []) {
            echo '<p>محصول منتشرشده‌ای پیدا نشد.</p>';
        }

        foreach (Fluids::all() as $key => $label) {
            $row = $recommendations[$key];
            $open = $row['volume'] !== '' || $row['products'] !== [];
            echo '<details class="mopico-oil-fluid"' . ($open ? ' open' : '') . '>';
            echo '<summary>' . esc_html($label) . '</summary>';
            echo '<p><label for="mopico-oil-volume-' . esc_attr($key) . '">حجم سیال مورد نیاز (لیتر)</label><br>';
            echo '<input class="widefat" type="text" id="mopico-oil-volume-' . esc_attr($key) . '" name="mopico_oil_volume[' . esc_attr($key) . ']" value="' . esc_attr($row['volume']) . '"></p>';
            echo '<div class="mopico-oil-product-list">';
            foreach ($choices as $choice) {
                echo '<label><input type="checkbox" name="mopico_oil_products[' . esc_attr($key) . '][]" value="' . esc_attr((string) $choice['id']) . '" ' . checked(in_array($choice['id'], $row['products'], true), true, false) . '> ';
                echo esc_html($choice['label']) . '</label>';
            }
            echo '</div></details>';
        }
    }

    private static function saveParent(int $postId, string $childType, string $metaKey, string $parentType): void
    {
        if (! self::canSave($postId, 'mopico_oil_parent_nonce', 'mopico_oil_parent_' . $postId)) {
            return;
        }
        if (get_post_type($postId) !== $childType) {
            return;
        }

        $parentId = self::postedParentId($parentType);
        self::rememberMissingParent($parentId);
        update_post_meta($postId, $metaKey, $parentId);
    }

    private static function rememberMissingParent(int $parentId): void
    {
        if ($parentId > 0 || self::$missingParent) {
            return;
        }

        self::$missingParent = true;
        add_filter('redirect_post_location', [self::class, 'flagMissingParent']);
    }

    public static function flagMissingParent(string $location): string
    {
        return add_query_arg('mopico_oil_missing_parent', '1', $location);
    }

    private static function postedParentId(string $parentType): int
    {
        $raw = $_POST['mopico_oil_parent'] ?? 0;
        $parentId = is_scalar($raw) ? absint((string) $raw) : 0;
        $parent = get_post($parentId);
        if (! $parent instanceof \WP_Post || $parent->post_type !== $parentType) {
            return 0;
        }
        if (! in_array($parent->post_status, ['publish', 'draft', 'pending'], true)) {
            return 0;
        }

        return $parentId;
    }

    /**
     * @return array<string, mixed>
     */
    private static function postedArray(string $key): array
    {
        $raw = $_POST[$key] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $clean = wp_unslash($raw);

        return is_array($clean) ? $clean : [];
    }

    private static function canSave(int $postId, string $field, string $action): bool
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        if (wp_is_post_revision($postId)) {
            return false;
        }
        if (! current_user_can('edit_post', $postId)) {
            return false;
        }

        $raw = $_POST[$field] ?? '';
        if (! is_string($raw)) {
            return false;
        }

        return (bool) wp_verify_nonce(sanitize_text_field(wp_unslash($raw)), $action);
    }

    /**
     * @return array<string, array{meta: string, column: string}>
     */
    private static function parents(): array
    {
        return [
            PostTypes::BRAND => [
                'meta' => PostTypes::BRAND_GROUP_META,
                'column' => 'گروه',
            ],
            PostTypes::MODEL => [
                'meta' => PostTypes::MODEL_BRAND_META,
                'column' => 'سازنده',
            ],
            PostTypes::VARIANT => [
                'meta' => PostTypes::VARIANT_MODEL_META,
                'column' => 'مدل',
            ],
        ];
    }

    public static function registerProductSettings(): void
    {
        register_setting('mopico_oil_products', SiteProducts::OPTION_POST_TYPE, [
            'type' => 'string',
            'sanitize_callback' => [SiteProducts::class, 'sanitizePostType'],
            'show_in_rest' => false,
            'default' => 'product',
        ]);
        register_setting('mopico_oil_products', SiteProducts::OPTION_TAXONOMY, [
            'type' => 'string',
            'sanitize_callback' => [SiteProducts::class, 'sanitizeTaxonomy'],
            'show_in_rest' => false,
            'default' => 'group',
        ]);
        register_setting('mopico_oil_products', SiteProducts::OPTION_LINE_META, [
            'type' => 'string',
            'sanitize_callback' => [SiteProducts::class, 'sanitizeLineMeta'],
            'show_in_rest' => false,
            'default' => 'red-title',
        ]);
    }

    public static function addProductSettingsPage(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . PostTypes::GROUP,
            'اتصال محصول',
            'اتصال محصول',
            'manage_options',
            'mopico-oil-products',
            [self::class, 'renderProductSettings']
        );
    }

    public static function renderProductSettings(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $postType = SiteProducts::postType();
        $taxonomy = SiteProducts::taxonomy();
        $lineMeta = SiteProducts::lineMeta();

        echo '<div class="wrap"><h1>اتصال محصول</h1>';
        echo '<p>مرحله نتیجه فانل از این نوع نوشته، تصویر شاخص و نام تجاری‌اش را می‌خواند.</p>';
        echo '<form method="post" action="options.php">';
        settings_fields('mopico_oil_products');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="mopico-oil-product-type">نوع نوشته محصول</label></th><td>';
        echo '<select id="mopico-oil-product-type" name="' . esc_attr(SiteProducts::OPTION_POST_TYPE) . '">';
        foreach (self::selectablePostTypes() as $type => $label) {
            echo '<option value="' . esc_attr($type) . '" ' . selected($postType, $type, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th scope="row"><label for="mopico-oil-product-tax">دسته‌بندی محصول</label></th><td>';
        echo '<select id="mopico-oil-product-tax" name="' . esc_attr(SiteProducts::OPTION_TAXONOMY) . '">';
        echo '<option value="" ' . selected($taxonomy, '', false) . '>بدون دسته‌بندی</option>';
        foreach (self::selectableTaxonomies($postType) as $name => $label) {
            echo '<option value="' . esc_attr($name) . '" ' . selected($taxonomy, $name, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th scope="row"><label for="mopico-oil-line-meta">کلید نام تجاری</label></th><td>';
        echo '<input class="regular-text" type="text" id="mopico-oil-line-meta" name="' . esc_attr(SiteProducts::OPTION_LINE_META) . '" value="' . esc_attr($lineMeta) . '">';
        echo '<p class="description">متای زیرعنوان محصول. در هیوندای اکستیر مقدار پیش‌فرض red-title است. خالی یعنی فقط عنوان محصول.</p>';
        echo '</td></tr></tbody></table>';
        submit_button();
        echo '</form></div>';
    }

    /**
     * @return array<string, string>
     */
    private static function selectablePostTypes(): array
    {
        $types = [];
        foreach (get_post_types(['show_ui' => true], 'objects') as $type) {
            if (in_array($type->name, PostTypes::slugs(), true)) {
                continue;
            }
            $types[$type->name] = $type->labels->singular_name . ' (' . $type->name . ')';
        }
        if (! isset($types['product']) && post_type_exists('product')) {
            $types['product'] = 'product';
        }

        return $types;
    }

    /**
     * @return array<string, string>
     */
    private static function selectableTaxonomies(string $postType): array
    {
        $taxes = [];
        if (! post_type_exists($postType)) {
            return $taxes;
        }
        foreach (get_object_taxonomies($postType, 'objects') as $taxonomy) {
            $taxes[$taxonomy->name] = $taxonomy->labels->singular_name . ' (' . $taxonomy->name . ')';
        }

        return $taxes;
    }
}
