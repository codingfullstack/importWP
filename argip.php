<?php



/**

 * ARGIP integracija by Emisija.lt

 *

 * @link              https://emisija.lt

 * @since             1.0.0

 *

 * @wordpress-plugin

 * Plugin Name:       Argip API

 * Plugin URI:        https://emisija.lt

 * Description:       Darbas su Argip API

 * Version:           1.0.0

 * Author:            Emisija

 * Author URI:        https://emisija.lt

 * Text Domain:       argip

 */


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly....
}

/**

 * Check if woocommerce plugin active

 */

include_once (ABSPATH . 'wp-admin/includes/plugin.php');

if (!is_plugin_active('woocommerce/woocommerce.php')) {

    return false;
}

/**
 * Include Plugin files
 */

include_once plugin_dir_path(__FILE__) . 'includes/cron/import-cron.php';
require_once plugin_dir_path(__FILE__) . 'admin/admin.php';
include_once plugin_dir_path(__FILE__) . 'admin/view/agrip-page-admin-kategorijos.php';
include_once plugin_dir_path(__FILE__) . '/includes/api.php';

register_activation_hook(__FILE__, 'install_argip_import_stock_cron');

add_filter('cron_schedules', 'argip_custom_cron_shedules');

function argip_custom_cron_shedules($schedules)
{
    if (!isset($schedules["5min"])) {
        $schedules["5min"] = array(
            'interval' => 5 * 60,
            'display' => __('Once every 5 minutes')
        );
    }

    return $schedules;
}

function install_argip_import_stock_cron()
{
    if (!wp_next_scheduled('argip_import_products_stock')) {
        wp_schedule_event(time(), '5min', 'argip_import_products_stock');
    }
}

add_action('argip_import_products_stock', 'argip_run_import_stock_cron');

function argip_run_import_stock_cron()
{
    import_products_to_woocommerce();
}





