<?php
/**
 * Plugin Name:     Stregpay Checkout
 * Version:         0.1.0
 * Author:          The WordPress Contributors
 * License:         GPL-2.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     stregpay-checkout
 *
 * @package         create-block
 */



add_action(
	'woocommerce_blocks_loaded',
	function () {
		require_once __DIR__ . '/blocks-integration.php';
	}
);

/**
 * Registers the slug as a block category with WordPress.
 */
function register_StregpayCheckout_block_category( $categories ) {
	return array_merge(
		$categories,
		[
			[
				'slug'  => 'stregpay-checkout',
				'title' => __( 'StregpayCheckout Blocks', 'stregpay-checkout' ),
			],
		]
	);
}

add_action( 'block_categories_all', 'register_StregpayCheckout_block_category', 10, 2 );


// Register Stregpay payment method for traditional checkout
add_action('woocommerce_init', function() {
    if (!class_exists('WC_Stregpay_Payment_Method')) {
        require_once __DIR__ . '/payment-gateway-integration.php';
    }
});

// Register for both traditional and block checkout
add_filter('woocommerce_payment_gateways', function($methods) {
    $methods[] = 'WC_Stregpay_Payment_Method';
    return $methods;
});

// For block checkout, we also use JavaScript registration
// The PHP gateway is needed for traditional checkout compatibility

// Register with WooCommerce Blocks
add_action('woocommerce_blocks_loaded', function() {
    if (class_exists('StregpayCheckout_Blocks_Integration')) {
        add_action('woocommerce_blocks_payment_method_type_registration', function($registry) {
            $registry->register(new StregpayCheckout_Blocks_Integration(
                Automattic\WooCommerce\Blocks\Package::container()->get(Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry::class)
            ));
        });
    }
});

// Register webhook endpoint
add_action('rest_api_init', function() {
    register_rest_route('stregpay/v1', '/webhook', array(
        'methods' => 'POST',
        'callback' => 'stregpay_handle_webhook',
        'permission_callback' => '__return_true'
    ));
});

/**
 * Handle Stregpay webhook for order status updates
 */
function stregpay_handle_webhook(WP_REST_Request $request) {
    $body = json_decode($request->get_body(), true);

    // TODO: Verify webhook signature
    // $signature = $request->get_header('X-Stregpay-Signature');

    if (isset($body['order_id']) && isset($body['status'])) {
        $order = wc_get_order($body['order_id']);

        if ($order) {
            // Update order status based on webhook
            if ($body['status'] === 'completed') {
                $order->update_status('processing', __('Payment confirmed via Stregpay', 'stregpay-checkout'));
                $order->payment_complete();
            } elseif ($body['status'] === 'failed') {
                $order->update_status('failed', __('Stregpay payment failed', 'stregpay-checkout'));
            }

            return rest_ensure_response(array('success' => true));
        }
    }

    return new WP_Error('invalid_webhook', 'Invalid webhook data', array('status' => 400));
}
