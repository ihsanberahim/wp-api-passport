<?php

namespace WpRestPassport;

use WP_REST_Request;
use WP_REST_Token;
use WP_REST_Server;
use WP_REST_Key_Pair;
use Faker\Factory;
use Roave\BetterReflection\BetterReflection;
use WP_Http;
use WP_REST_Response;

class RestApi{
    const NAMESPACE = 'passport-wp-rest';
    const _WP_REST_URI = '/wp-json';

    private $routes = [
        '/exchange-credentials'
    ];

    function __construct()
    {
        add_action( 'rest_api_init', [$this, 'register_routes']);
        add_filter( 'rest_authentication_errors', [$this, 'require_authentication_checking'], 99, 1);
        add_filter( 'rest_authentication_require_token', [$this, 'bypass_token_checking'], 10, 3);
    }

    public function register_routes() {
        register_rest_route( static::NAMESPACE, $this->routes[0], array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'exchange_credentials']
          ) );
    }

    public function exchange_credentials(WP_REST_Request $request) {
        $input = collect($request->get_body_params());
        $username = $input->get('username', null);
        $password = $input->get('password', null);

        $user = wp_authenticate($username, $password);

        if( is_wp_error($user) ) {
            return $user;
        }

        $wp_rest_keypair = new WP_REST_Key_Pair;

        $existing_keypairs = $wp_rest_keypair->get_user_key_pairs($user->ID);

        $keypair_name = $user->display_name . ' ('. count($existing_keypairs) .')';

        $request->set_body_params([
            'name' => $keypair_name,
            'user_id' => $user->ID
        ]);

        wp_set_current_user( $user->ID );

        $user->add_cap( 'edit_user' );
        
        $keypair = $wp_rest_keypair->generate_key_pair($request);

        $user->remove_cap( 'edit_user' );

        wp_logout();

        $access_token_api = get_site_url() . static::_WP_REST_URI . '/wp/v2/token';

        $result = wp_remote_post($access_token_api, [
            'body' => [
                'username' => $username,
                'password' => $password,
                'api_key' => $keypair->row->api_key,
                'api_secret' => $keypair->api_secret
            ]
        ]);

        if( is_wp_error($result) ) {
            return $result;
        }

        $data = json_decode($result['body']);

        unset($data->data);

        return $data;
    }

    public function bypass_token_checking($require_token, $request_uri, $request_method) {
        if( in_array($request_uri, [ static::_WP_REST_URI . $this->routes[0] ]) ) {
            return false;
        }
        
        return $require_token;
    }

    public function require_authentication_checking($result) {
        if( in_array($this->get_current_rest_uri(), [ static::_WP_REST_URI . $this->routes[0] ]) ) {
            return false;
        }

        return true;
    }

    public function get_current_rest_uri() {
        return isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : false;
    }
}