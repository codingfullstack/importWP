<?php

function fetch_category_from_api($category_id)
{
    $token_url = 'https://identityserver.argip.com.pl/connect/token';
    $api_access_token = get_transient('api_access_token');
    $api_access_token_expires = get_transient('api_access_token_expires');
    $client_id = get_option('dbi_plugin_setting_client_id');
    $client_secret = get_option('dbi_plugin_setting_client_secret');
    $categories = array();

    if (!$api_access_token || $api_access_token_expires < time()) {
        API_auth($token_url, $client_id, $client_secret);
        $api_access_token = get_transient('api_access_token');
        $api_access_token_expires = get_transient('api_access_token_expires');
    }

    if (!empty($api_access_token)) {
        $categories_endpoint = 'https://argipapi.argip.com.pl/v1/Categories/' . $category_id;
        $categories = callAPI($categories_endpoint, $api_access_token);
    }

    if (!empty($categories) && is_array($categories)) {
        return $categories;
    } else {
        return 'Klaida gaunant API prieigą arba kategorijas.';
    }
}

function fetch_products_from_api($pageSize = 35, $page)
{
    $token_url = 'https://identityserver.argip.com.pl/connect/token';
    $api_access_token = get_transient('api_access_token');
    $api_access_token_expires = get_transient('api_access_token_expires');
    $client_id = get_option('dbi_plugin_setting_client_id');
    $client_secret = get_option('dbi_plugin_setting_client_secret');

    if (!$api_access_token || $api_access_token_expires < time()) {
        API_auth($token_url, $client_id, $client_secret);

        $api_access_token = get_transient('api_access_token');
    }

    $endpoint = "https://argipapi.argip.com.pl/v1/Products/{$page}/{$pageSize}/true";
    $data = callAPI($endpoint, $api_access_token);

    if (!$data || empty($data)) {
        return false;
    }

    return $data;
}

function get_products_by_sku($sku)
{

	global $wpdb;

	$product_ids = $wpdb->get_results($wpdb->prepare("SELECT post_id FROM " . $wpdb->prefix . "postmeta WHERE meta_key='_sku' AND meta_value='%s'", $sku));

	return $product_ids;
}


function import_products_to_woocommerce($pageSize = 35)
{
    // LOAD THE WC LOGGER
    $logger = wc_get_logger();
    $page = get_option('argip_current_page') ?: 1;

    $products_data = fetch_products_from_api($pageSize, $page);

    if (!$products_data) {
        $logger->info('Klaida gaunant produktus iš API puslapio.' . $page, array('source' => 'argip-failed-imports'));
        return false;
    }

    foreach ($products_data as $product_data) {
        $product = new WC_Product_Simple();

        if (empty($product_data['Index'])) {
            continue;
        }
        
        $product_in_shop = get_products_by_sku($product_data['Index']);

        if (!empty($product_in_shop)) {
            continue;
        }

        $product->set_sku($product_data['Index']);

        if (!empty($product_data['ProductFullName'])) {
            $product->set_name($product_data['ProductFullName']);
        }

        if (!empty($product_data['YourMainPrice'])) {
            $product->set_regular_price($product_data['YourMainPrice']);
        }

        $product->set_manage_stock(true);

        if (!empty($product_data['PiecesInStock'])) {
            $product->set_stock_quantity($product_data['PiecesInStock']);
        }

        if (!empty($product_data['BoxWeight'])) {
            $product->set_weight($product_data['BoxWeight']);
        }

        $image_url = $product_data['PictureUrl'];
        // $image_tmp_name = download_url($image_url);

        $product_images = [];

        if (!empty($image_url)) {
            $product_img_upload_single = wc_rest_upload_image_from_url(esc_url_raw($image_url));
            $product_img_id_single = wc_rest_set_uploaded_image_as_attachment($product_img_upload_single, $product->get_id());

            // Add image ID to array.
            $product_images[] = $product_img_id_single;
        }

        if (!empty($product_images)) {
            $product->set_image_id($product_images[0]);
            array_shift($product_images);
            $product->set_gallery_image_ids($product_images);
        }

        // $image_tmp_name = download_url($image_url);

        // if (!is_wp_error($image_tmp_name)) {
        //     $image_data = array(
        //         'name' => basename($image_url),
        //         'tmp_name' => $image_tmp_name
        //     );
        //     $image_id = media_handle_sideload($image_data, 0);

        //     if (!is_wp_error($image_id)) {
        //         $product->set_image_id($image_id);
        //     } else {
        //         // echo "Klaida įkeliant nuotrauką: " . $image_id->get_error_message();
        //         $logger->info('Klaida įkeliant nuotrauką (' . $product_data['Index'] . '):' . $image_id->get_error_message(), array('source' => 'argip-failed-imports'));
        //     }

        //     @unlink($image_tmp_name); // Ištriname laikiną nuotraukos failą
        // } else {
        //     // echo "Klaida atsisiunčiant nuotrauką: " . $image_tmp_name->get_error_message();
        //     $logger->info('Klaida atsisiunčiant nuotrauką (' . $product_data['Index'] . '):' . $image_tmp_name->get_error_message(), array('source' => 'argip-failed-imports'));
        // }

        $product_id = $product->save();

        if (!$product_id || is_wp_error($product_id)) {
            continue;
        }

        $category_id = $product_data['CategoryMapping'][0];

        $get_category_from_api = array();

        if (!empty($category_id)) {
            $get_category_from_api = fetch_category_from_api($category_id);
        }

        $category_name = '';

        if (!empty($get_category_from_api)) {
            foreach ($get_category_from_api['PathElements'] as $category) {
                if ($category['ParentCategoryId'] == 0) {
                    $category_name = $category['Name'];
                }
            }
        }

        $category_ids_array = array(24);

        if (!empty($category_name)) {

            $parent_term = term_exists($category_name, 'product_cat'); // array is returned if taxonomy is given

            if (!is_array($parent_term)) {
                $term_data = wp_insert_term(
                    $category_name,
                    'product_cat',
                    array(
                        'parent' => 24
                    )
                );

                $parent_term_inserted = term_exists($category_name, 'product_cat'); // array is returned if taxonomy is given

                if (!empty($parent_term_inserted)) {
                    array_push($category_ids_array, $parent_term_inserted['term_id']);
                }
            } else {
                array_push($category_ids_array, $parent_term['term_id']);
            }
        }

        $product->set_category_ids($category_ids_array);
        $product->save();
    }

    $page++;

    // set_transient('page', $page, 24 * HOUR_IN_SECONDS);
    update_option('argip_current_page', $page);

    if (count($products_data) < $pageSize) {
        update_option('argip_current_page', 1);
    }

    return true;
}

// *********************
// ******CRON JOB*******
// *********************
// ***********
// TEST CRON
// ***********
// add_filter('cron_schedules', 'add_one_minute_interval');
// function add_one_minute_interval($schedules)
// {
//     $schedules['one_minute'] = array(
//         'interval' => 60,
//         'display' => 'Kas minute',
//     );
//     return $schedules;
// }

// // Nustatome cron darbuotoją
// function schedule_argip_import_event()
// {
//     if (!wp_next_scheduled('argip_import_event')) {
//         wp_schedule_event(time(), 'one_minute', 'argip_import_event');
//     }
// }
// add_action('init', 'schedule_argip_import_event');
// add_action('argip_import_event', 'import_products_to_woocommerce');
// Pridedame custom intervalą

// add_filter('cron_schedules', 'add_custom_night_interval');
// function add_custom_night_interval($schedules)
// {
//     $schedules['night_interval'] = array(
//         'interval' => 1800, // pusvalandis yra 1800 sekundžių
//         'display' => 'Kiekvieną pusvalandį nuo 2 iki 6 nakties',
//     );
//     return $schedules;
// }


// function schedule_argip_import_event()
// {
//     if (!wp_next_scheduled('argip_import_event')) {
//         $now = current_time('timestamp');
//         $start_time = strtotime(date('Y-m-d 02:00:00', $now));
//         $end_time = strtotime(date('Y-m-d 06:00:00', $now));
//         if ($now > $end_time) {
//             $start_time = strtotime('+1 day', $start_time);
//             $end_time = strtotime('+1 day', $end_time);
//         }
//         while ($start_time <= $end_time) {
//             wp_schedule_event($start_time, 'night_interval', 'argip_import_event');
//             $start_time += 1800; 
//         }
//     }
// }
// add_action('init', 'schedule_argip_import_event');
// add_action('argip_import_event', 'import_products_to_woocommerce');