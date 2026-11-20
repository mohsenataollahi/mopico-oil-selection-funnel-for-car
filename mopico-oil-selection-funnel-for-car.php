<?php
/**
 * Plugin Name: انتخاب روغن خودرو
 * Description:افزونه فانل انتخاب روغن خودرو برای فانل های مجموعه موپیکو 
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Mohsen Ataollahi
 * Author URI: https://github.com/mohsenataollahi/
 * Text Domain: mopico-oil-selection-funnel-for-car
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('MOPICO_OIL_FILE', __FILE__);
define('MOPICO_OIL_DIR', plugin_dir_path(__FILE__));
define('MOPICO_OIL_URL', plugin_dir_url(__FILE__));
define('MOPICO_OIL_VERSION', '1.0.0');

require_once MOPICO_OIL_DIR . 'includes/class-post-types.php';
require_once MOPICO_OIL_DIR . 'includes/class-fluids.php';
require_once MOPICO_OIL_DIR . 'includes/class-site-products.php';
require_once MOPICO_OIL_DIR . 'includes/class-catalog.php';
require_once MOPICO_OIL_DIR . 'includes/class-admin.php';
require_once MOPICO_OIL_DIR . 'includes/class-shortcode.php';
require_once MOPICO_OIL_DIR . 'includes/class-plugin.php';

register_activation_hook(MOPICO_OIL_FILE, static function (): void {
    Mopico\OilFunnel\PostTypes::register();
    flush_rewrite_rules();
});

register_deactivation_hook(MOPICO_OIL_FILE, 'flush_rewrite_rules');

add_action('plugins_loaded', [Mopico\OilFunnel\Plugin::class, 'boot']);
