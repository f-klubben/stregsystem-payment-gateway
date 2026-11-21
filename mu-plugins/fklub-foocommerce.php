<?php
/**
 * Plugin Name: F-Klub Foocommerce
 * Description: Stregdollar (FFF) currency and fff storefront CSS from the foocommerce repo.
 */

defined('ABSPATH') || exit;

const FKLUB_FOOCOMMERCE_PATH = '/opt/foocommerce';

// The filters are in a Storefront child theme; the packaged plugin wins if it is ever committed.
foreach (array(
    FKLUB_FOOCOMMERCE_PATH . '/WooCommerce-Stregdollar-Currency/woocommerce-stregdollar-currency.php',
    FKLUB_FOOCOMMERCE_PATH . '/StregdollarCurrencyTheme/functions.php',
) as $fklub_currency) {
    if (is_readable($fklub_currency)) {
        require_once $fklub_currency;
        break;
    }
}
unset($fklub_currency);

add_action('wp_enqueue_scripts', static function () {
    $css = FKLUB_FOOCOMMERCE_PATH . '/custom-wp-dokuwiki-theme.css';
    if (is_readable($css)) {
        wp_register_style('fklub-stregdollar', false);
        wp_enqueue_style('fklub-stregdollar');
        wp_add_inline_style('fklub-stregdollar', file_get_contents($css));
    }
}, 100);