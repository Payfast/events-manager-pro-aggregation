<?php
/**
 * Plugin Name:       Payfast Aggregation for Events Manager Pro
 * Plugin URI:        https://payfast.io/integration/plugins/events-manager-pro/
 * Description:       Payfast payment gateway for Events Manager Pro. Requires Events Manager and Events Manager Pro.
 * Version:           1.3.0
 * Author:            Payfast
 * Author URI:        https://payfast.io/
 * License:           GPL-2.0-or-later
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Text Domain:       em-payfast-gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'EM_PAYFAST_VERSION',     '1.3.0' );
define( 'EM_PAYFAST_PLUGIN_FILE', __FILE__ );
define( 'EM_PAYFAST_DIR',         plugin_dir_path( __FILE__ ) );
define( 'EM_PAYFAST_URL',         plugin_dir_url( __FILE__ ) );

require_once EM_PAYFAST_DIR . 'vendor/autoload.php';
require_once EM_PAYFAST_DIR . 'payfast/constants.php';

// ---------------------------------------------------------------------------
// Gateway registration
// ---------------------------------------------------------------------------
// Hook to em_gateways_init (registered here, at plugin load time, so it is in
// place before EM Pro fires the hook during its own plugins_loaded handler).
// This is the correct entry point for standalone gateway plugins in EM Pro 3.2+.
add_action( 'em_gateways_init', 'em_payfast_load_gateway' );

// Fallback: also try at plugins_loaded priority 20 in case em_gateways_init has
// already fired or is not used by this version of EM Pro.
add_action( 'plugins_loaded', 'em_payfast_load_gateway', 20 );

/**
 * Load and initialise the gateway + admin classes.
 * A static flag prevents double-initialisation if both hooks fire.
 */
function em_payfast_load_gateway() {
    static $loaded = false;
    if ( $loaded ) {
        return;
    }

    // EM Pro base classes must exist before we can extend them.
    if ( ! class_exists( '\EM\Payments\Gateway' ) ) {
        return;
    }

    // Ensure the EMP base Gateway_Admin class is loaded before our subclass.
    // EMP only includes it lazily via Gateway::admin(), so we must load it explicitly.
    if ( ! class_exists( '\EM\Payments\Gateway_Admin' ) && defined( 'EMP_DIR' ) ) {
        require_once EMP_DIR . '/add-ons/gateways/gateway-admin.php';
    }

    require_once EM_PAYFAST_DIR . 'includes/GatewayAdmin.php';
    require_once EM_PAYFAST_DIR . 'includes/Gateway.php';

    // Gateway::init() is called at the bottom of Gateway.php.
    // Explicitly call Gateway_Admin::init() so its hooks are registered too.
    \EM\Payments\Payfast\Gateway_Admin::init();

    $loaded = true;
}

// ---------------------------------------------------------------------------
// General plugin bootstrap (dependency notices, ZAR currency)
// ---------------------------------------------------------------------------
add_action( 'plugins_loaded', 'em_payfast_init', 20 );

function em_payfast_init() {
    // Dependency check: Events Manager
    if ( ! function_exists( 'em_get_booking' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'Payfast for Events Manager Pro requires the Events Manager plugin to be active.', 'em-payfast-gateway' )
                . '</p></div>';
        } );
        return;
    }

    // Dependency check: Events Manager Pro new-style gateway base class
    if ( ! class_exists( '\EM\Payments\Gateway' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'Payfast for Events Manager Pro requires Events Manager Pro (v3.2+) to be active.', 'em-payfast-gateway' )
                . '</p></div>';
        } );
        return;
    }

    // ZAR currency — replaces the manual em-functions.php edit
    add_filter( 'em_get_currencies', 'em_payfast_add_zar_currency' );
}

function em_payfast_add_zar_currency( $currencies ) {
    $currencies->names['ZAR']        = 'ZAR - South African Rand';
    $currencies->symbols['ZAR']      = 'R';
    $currencies->true_symbols['ZAR'] = 'R';
    return $currencies;
}

register_activation_hook( EM_PAYFAST_PLUGIN_FILE, 'em_payfast_activate' );
register_deactivation_hook( EM_PAYFAST_PLUGIN_FILE, 'em_payfast_deactivate' );

function em_payfast_activate() {
    // Nothing required for now — gateway activates itself via EM Pro admin UI.
}

function em_payfast_deactivate() {
    wp_clear_scheduled_hook( 'emp_payfast_cron' );
}
