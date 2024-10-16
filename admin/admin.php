<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly....
}

//Plugin menu setup
add_action('admin_menu', 'agrip_plugin_setup_menu');
function agrip_plugin_setup_menu()
{
    add_menu_page('ARGIP', 'ARGIP', 'administrator', 'argip', 'argip_init');
    add_submenu_page('argip', 'Kategorijos', 'Kategorijos', 'administrator', 'argip_kategorijos', 'argip_init_kategorijos');
}

// Init function general AGRIP settings page
function argip_init()
{
    if (isset($_POST['submit'])) {

        if (!empty($_POST['dbi_plugin_setting_client_id']) && !empty($_POST['dbi_plugin_setting_client_secret'])) {

            $client_id = sanitize_text_field($_POST['dbi_plugin_setting_client_id']);
            $client_secret = sanitize_text_field($_POST['dbi_plugin_setting_client_secret']);
            $products_import = sanitize_text_field($_POST['dbi_plugin_setting_products_import']);

            update_option('dbi_plugin_setting_client_id', $client_id);
            update_option('dbi_plugin_setting_client_secret', $client_secret);
            update_option('dbi_plugin_setting_products_import', $products_import);

        } else {
            echo '<div class="error"><p>Įveskite API raktai ir vartotojo ID.</p></div>';
        }
    }

    include plugin_dir_path(__FILE__) . 'view/agrip-page-admin-settings.php';

}

// ***********************************

// GENERATE A CATEGORY PAGE

// **********************************

function argip_init_kategorijos()
{
    $token_url = 'https://identityserver.argip.com.pl/connect/token';
    $api_access_token = get_transient('api_access_token');
    $api_access_token_expires = get_transient('api_access_token_expires');
    $client_id = get_option('dbi_plugin_setting_client_id');
    $client_secret = get_option('dbi_plugin_setting_client_secret');
    $transient_categories = get_transient('argip_categories');

    if (!$api_access_token || $api_access_token_expires < time()) {
        API_auth($token_url, $client_id, $client_secret);
        $api_access_token = get_transient('api_access_token');
        $api_access_token_expires = get_transient('api_access_token_expires');
    }

    ?>
    <h2>Kategorijos</h2>
    <?php

    if (!$transient_categories || empty($transient_categories)) {
        $categories_endpoint = 'https://argipapi.argip.com.pl/v1/Categories';
        $categories = callAPI($categories_endpoint, $api_access_token);

        if ($categories) {
            set_transient('argip_categories', $categories, 24 * HOUR_IN_SECONDS);
            $transient_categories = $categories;
        }
    }

    if ($transient_categories) {
        printFunction($transient_categories);
    } else {
        echo 'Klaida gaunant API prieigą arba kategorijas.';
    }
}

add_action('admin_init', 'dbi_register_settings');

function dbi_register_settings()
{
    register_setting('agrip_plugin', 'dbi_plugin_setting_client_id');
    register_setting('agrip_plugin', 'dbi_plugin_setting_client_secret');

    add_settings_section('api_settings', 'Argip IPA', 'dbi_plugin_section_text', 'agrip_plugin');

    add_settings_field('dbi_plugin_setting_client_id', 'Client ID', 'dbi_plugin_setting_client_id_callback', 'agrip_plugin', 'api_settings');
    add_settings_field('dbi_plugin_setting_client_secret', 'Client Secret', 'dbi_plugin_setting_client_secret_callback', 'agrip_plugin', 'api_settings');
    add_settings_field('dbi_plugin_setting_products_import', 'Products Import', 'dbi_plugin_setting_products_import_callback', 'agrip_plugin', 'api_settings');
}

function dbi_plugin_section_text()
{

}

function dbi_plugin_setting_products_import_callback()
{
    $products_import = get_option('dbi_plugin_setting_products_import');

    if (!empty($products_import)) {
        $products_import = 'checked';
    }

    printf(

        '<input class="regular-text" type="checkbox" name="dbi_plugin_setting_products_import" id="dbi_plugin_setting_products_import" %s>',

        isset($products_import) ? esc_attr($products_import) : ''

    );

}

function dbi_plugin_setting_client_id_callback()
{
    $client_id = get_option('dbi_plugin_setting_client_id');
    echo '<input type="text" id="dbi_plugin_setting_client_id" name="dbi_plugin_setting_client_id" value="' . esc_attr($client_id) . '" />';
}

function dbi_plugin_setting_client_secret_callback()
{
    $client_secret = get_option('dbi_plugin_setting_client_secret');
    echo '<input type="text" id="dbi_plugin_setting_client_secret" name="dbi_plugin_setting_client_secret" value="' . esc_attr($client_secret) . '" />';
}