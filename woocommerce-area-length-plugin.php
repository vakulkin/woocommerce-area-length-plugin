<?php

/**
 * Plugin Name: WooCommerce Area and Length Products
 * Description: Plugin for managing area-based and length-based products in WooCommerce with dynamic quantity calculations.
 * Version: 1.0.0
 * Text Domain: woocommerce-area-length-plugin
 * Domain Path: /languages
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WALP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WALP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load plugin text domain for translations
function walp_load_textdomain()
{
    load_plugin_textdomain('woocommerce-area-length-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'walp_load_textdomain');

// Include necessary files
require_once WALP_PLUGIN_DIR . 'includes/class-product-handler.php';

// Initialize classes
$product_handler = new WALP_Product_Handler();

// Enqueue scripts and styles
function walp_enqueue_scripts()
{
    wp_enqueue_style('walp-styles', WALP_PLUGIN_URL . 'assets/css/styles.css');
    wp_enqueue_script('walp-script', WALP_PLUGIN_URL . 'assets/js/script.js', array( 'jquery' ), '1.0.0', true);

    // Get WooCommerce currency settings
    $currency_symbol = get_woocommerce_currency_symbol();
    $currency_pos = get_option('woocommerce_currency_pos', 'left');
    $price_decimal_sep = wc_get_price_decimal_separator();
    $price_thousand_sep = wc_get_price_thousand_separator();
    $price_decimals = wc_get_price_decimals();

    // Localize script with translations and currency settings
    wp_localize_script('walp-script', 'walpData', array(
        'currency' => array(
            'symbol' => html_entity_decode($currency_symbol),
            'position' => $currency_pos,
            'decimalSeparator' => $price_decimal_sep,
            'thousandSeparator' => $price_thousand_sep,
            'decimals' => $price_decimals
        ),
        'i18n' => array(
            'atLeast' => __('at least:', 'woocommerce-area-length-plugin'),
            'weHave' => __('we have', 'woocommerce-area-length-plugin'),
            'of' => __('of', 'woocommerce-area-length-plugin'),
            'inStock' => __('in stock', 'woocommerce-area-length-plugin'),
            'squareMeters' => __('m²', 'woocommerce-area-length-plugin'),
            'meters' => __('m', 'woocommerce-area-length-plugin'),
            'pieces' => __('pcs', 'woocommerce-area-length-plugin')
        )
    ));
}
add_action('wp_enqueue_scripts', 'walp_enqueue_scripts');
