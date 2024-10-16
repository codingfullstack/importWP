<?php
// *******************************
// ***API AUTHORIZATION***** 
// *******************************
function API_auth($token_url, $client_id, $client_secret)
{
    $token_response = wp_remote_post($token_url, [
        'body' => [
            'grant_type' => 'client_credentials',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
        ],
    ]);

    if (wp_remote_retrieve_response_code($token_response) == 200) {
        $body = json_decode(wp_remote_retrieve_body($token_response), true);
        $token = $body['access_token'];
        $expires_in = $body['expires_in'];
        $expiration_time = time() + $expires_in;
        // Store the token and expiration time
        set_transient('api_access_token', $token, $expires_in);
        set_transient('api_access_token_expires', $expiration_time, $expires_in);
        echo '<div class="updated"><p>API raktai ir prieigos žetonas sėkmingai išsaugoti!</p></div>';
    } else {
        echo '<div class="error"><p>Neteisingi API raktai arba autentifikacija nepavyko.</p></div>';
    }
}
// *******************************
// **********GET DATA FROM API****
// *******************************
function callAPI($endpoint, $token)
{
    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
        ),
    );
    $response = wp_remote_get($endpoint, $args);

    // Check if the request was successful
    if (is_wp_error($response)) {
        echo '<p>Klaida bandant gauti duomenis: ' . esc_html($response->get_error_message()) . '</p>';
        return false;
    }

    // Check HTTP status code
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code != 200) {
        echo '<p>Klaida: Negautas 200 HTTP status kodas, gautas ' . esc_html($response_code) . '</p>';
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
}