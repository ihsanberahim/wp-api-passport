<?php
/**
 * Plugin Name: WP REST Passport
 * Description: Seamless rest integration. 'JWT Authentication for WP-API' is required.
 * Version: 1.0.0
 * License: GPL2+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package WpRestPassport
 */

require_once __DIR__ . '/vendor/autoload.php';

try{
    WpRestPassport\Provider::register();
} catch (\Exception $e) {
    add_action( 'admin_notices', function() use ($e) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php _e( $e->getMessage() ); ?></p>
        </div>
        <?php
    } );
}
