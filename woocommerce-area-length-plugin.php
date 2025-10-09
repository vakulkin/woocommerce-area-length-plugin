<?php

/**
 * Plugin Name: WooCommerce Produkty Powierzchniowe i Długościowe
 * Description: Wtyczka do zarządzania produktami powierzchniowymi i długościowymi w WooCommerce z dynamicznymi obliczeniami ilości.
 * Text Domain: woocommerce-area-length-plugin
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WALP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WALP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once WALP_PLUGIN_DIR . 'includes/class-product-handler.php';

// Initialize classes
$product_handler = new WALP_Product_Handler();

// Enqueue scripts and styles
function walp_enqueue_scripts()
{
    wp_enqueue_style('walp-styles', WALP_PLUGIN_URL . 'assets/css/styles.css');
    wp_enqueue_script('walp-script', WALP_PLUGIN_URL . 'assets/js/script.js', array( 'jquery' ), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'walp_enqueue_scripts');
