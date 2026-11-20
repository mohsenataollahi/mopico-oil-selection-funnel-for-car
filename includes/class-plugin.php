<?php

declare(strict_types=1);

namespace Mopico\OilFunnel;

if (! defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    public static function boot(): void
    {
        add_action('init', [PostTypes::class, 'register']);
        add_action('init', [self::class, 'loadTextDomain']);
        Admin::boot();
        Shortcode::boot();
        add_action('save_post', [Catalog::class, 'forgetSavedPost'], 10, 2);
        add_action('deleted_post', [Catalog::class, 'forgetSavedPost'], 10, 2);
        add_action('added_option', [Catalog::class, 'forgetOption']);
        add_action('updated_option', [Catalog::class, 'forgetOption']);
    }

    public static function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'mopico-oil-selection-funnel-for-car',
            false,
            dirname(plugin_basename(MOPICO_OIL_FILE)) . '/languages'
        );
    }
}
