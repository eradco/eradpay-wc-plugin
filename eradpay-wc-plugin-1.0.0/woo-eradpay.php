<?php
/**
 * Plugin Name: eradPay for WooCommerce
 * Plugin URI: https://erad.co/
 * Description: eradPay payment gateway integration for WooCommerce version 2.0.0 or greater version.
 * Version: 1.0.0
 * Author: Team erad
 * Author URI: https://erad.co/
 * Requires at least: 5.6
 * Requires PHP: 7.2
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('ERADPAY_PLUGIN_PATH', plugin_dir_url(__FILE__));

function woocommerce_eradpay_init() {
    if (! class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once __DIR__.'/includes/class-wc-eradpay-gateway.php';

    # Generate plugin links
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), [WC_eradPay_Gateway::class, 'add_plugin_links']);

    # Init payment gateway
    add_filter('woocommerce_payment_gateways', [WC_eradPay_Gateway::class, 'init']);
}

# Plugin initialization
add_action('plugins_loaded', 'woocommerce_eradpay_init', 0);

