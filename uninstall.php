<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/class-post-types.php';
require_once __DIR__ . '/includes/class-site-products.php';
require_once __DIR__ . '/includes/class-catalog.php';

delete_transient(Mopico\OilFunnel\Catalog::CACHE_KEY);
delete_option(Mopico\OilFunnel\SiteProducts::OPTION_POST_TYPE);
delete_option(Mopico\OilFunnel\SiteProducts::OPTION_TAXONOMY);
delete_option(Mopico\OilFunnel\SiteProducts::OPTION_LINE_META);

foreach (Mopico\OilFunnel\PostTypes::slugs() as $slug) {
    $ids = get_posts([
        'post_type' => $slug,
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
    ]);

    foreach ($ids as $id) {
        wp_delete_post((int) $id, true);
    }
}
