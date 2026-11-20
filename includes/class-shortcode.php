<?php

declare(strict_types=1);

namespace Mopico\OilFunnel;

if (! defined('ABSPATH')) {
    exit;
}

final class Shortcode
{
    private const STYLE = 'mopico-oil-funnel';

    private const SCRIPT = 'mopico-oil-funnel';

    private static int $count = 0;

    public static function boot(): void
    {
        add_shortcode('mopico_oil_funnel', [self::class, 'render']);
    }

    public static function render(): string
    {
        $tree = Catalog::tree();
        if ($tree['groups'] === []) {
            return '<p class="mopico-oil-empty">هنوز گروهی برای فانل ثبت نشده.</p>';
        }

        self::registerAssets();
        wp_enqueue_style(self::STYLE);
        wp_enqueue_script(self::SCRIPT);

        $json = wp_json_encode(
            $tree,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        if (! is_string($json)) {
            return '<p class="mopico-oil-empty">داده‌های فانل ساخته نشد.</p>';
        }

        $index = self::$count;
        self::$count++;
        wp_add_inline_script(self::SCRIPT, 'window.mopicoOilFunnels = window.mopicoOilFunnels || []; window.mopicoOilFunnels.push(' . $json . ');', 'before');

        return '<div class="mopico-oil" dir="rtl" data-mopico-oil data-index="' . esc_attr((string) $index) . '">'
            . '<div class="mopico-oil-steps"></div>'
            . '<div class="mopico-oil-stage"></div>'
            . '<p class="mopico-oil-message" role="status"></p>'
            . '<div class="mopico-oil-nav"></div>'
            . '<noscript>برای دیدن فانل جاوااسکریپت لازم است.</noscript>'
            . '</div>';
    }

    private static function registerAssets(): void
    {
        if (wp_style_is(self::STYLE, 'registered')) {
            return;
        }

        wp_register_style(
            self::STYLE,
            MOPICO_OIL_URL . 'assets/funnel.css',
            [],
            MOPICO_OIL_VERSION
        );
        wp_register_script(
            self::SCRIPT,
            MOPICO_OIL_URL . 'assets/funnel.js',
            [],
            MOPICO_OIL_VERSION,
            true
        );
    }
}
