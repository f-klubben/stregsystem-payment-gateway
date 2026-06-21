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

    if (!isset($body['status']) || !isset($body['id'])) {
        return new WP_Error('invalid_webhook', 'Invalid webhook data', array('status' => 400));
    }

    error_log('[STREGPAY CHECKOUT] Webhook request' . print_r($body, true));

    $intent_id = $body['id'];
    $intent_status = $body['status'];

    if ($intent_status == 'I'){
        // Intent has just been initialized
        // We don't have an Intent ID connected to an order yet.
        return rest_ensure_response(array('success' => true));
    }

    $orders = wc_get_orders([
        'limit'      => 1,
        'meta_key'   => '_stregsystem_intent_id',
        'meta_value' => $intent_id,
    ]);

    if (empty($orders)) {
        error_log('[STREGPAY CHECKOUT] Webhook request - order not found');
        return new WP_Error('invalid_webhook', 'Order not found', array('status' => 400));
    }

    $order = $orders[0];

    if ($intent_status == 'P') {
        // On-hold
        $order->update_status('on-hold', __('Payment pending funds (via Stregpay)', 'stregpay-checkout'));
    } elseif ($intent_status == 'F') {
        // Finalized, funds secured
        $order->update_status('processing', __('Payment confirmed (via Stregpay)', 'stregpay-checkout'));
        $order->payment_complete();
    } elseif ($intent_status == 'A' || $intent_status == 'E' || $intent_status == 'C') {
        // Aborted, cancelled or expired
        $order->update_status('failed', __('Stregpay payment failed', 'stregpay-checkout'));
        $order->payment_complete();
    } else {
        return new WP_Error('invalid_webhook', 'Invalid webhook data', array('status' => 400));
    }

    return rest_ensure_response(array('success' => true));
}

/**
 * Warn if permalinks are set to "Plain", which breaks the Stregpay webhook endpoint.
 */
add_action( 'admin_notices', function () {
	if ( get_option( 'permalink_structure' ) === '' ) {
		?>
		<div class="notice notice-error">
			<p>
				<strong>Stregpay Checkout:</strong>
				<?php
				printf(
					/* translators: %s: link to permalinks settings page */
					esc_html__( 'Your site uses "Plain" permalinks, which prevents the Stregpay webhook endpoint from working. Please change your permalink structure in %s to anything other than "Plain".', 'stregpay-checkout' ),
					'<a href="' . esc_url( admin_url( 'options-permalinks.php' ) ) . '">' . esc_html__( 'Settings → Permalinks', 'stregpay-checkout' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
} );
