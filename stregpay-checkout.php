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
    } elseif ($intent_status == 'A' || $intent_status == 'E' || $intent_status == 'C') {
        // Aborted, cancelled or expired
        $order->update_status('failed', __('Stregpay payment failed', 'stregpay-checkout'));
    } else {
        return new WP_Error('invalid_webhook', 'Invalid webhook data', array('status' => 400));
    }

    return rest_ensure_response(array('success' => true));
}

/**
 * Helper: Normalize product names for accurate comparison (strip tags, decode entities, normalize spaces).
 */
function stregpay_normalize_product_name($name) {
    $name = str_replace(['<br>', '<br/>', '<br />'], ' ', (string) $name);
    $name = wp_strip_all_tags($name);
    $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $name));
}

/**
 * Helper: Format price in major currency units (e.g. DKK) cleanly for notices and tooltips.
 */
function stregpay_format_price($amount) {
    if ($amount === null || $amount === '') {
        return __('None', 'stregpay-checkout');
    }
    if (function_exists('wc_price')) {
        return html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return number_format((float) $amount, 2, ',', '.') . ' kr.';
}

/**
 * Fetch active products from Stregsystem API, cached in a transient.
 *
 * @param bool $force_refresh
 * @return array|null Associative array of [id => ['name' => ..., 'price' => ...]] or null on failure.
 */
function stregpay_get_active_products($force_refresh = false) {
    $transient_key = 'stregpay_active_products';

    if (!$force_refresh) {
        $cached = get_transient($transient_key);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }
    }

    $settings = get_option('woocommerce_stregpay_settings', []);
    $endpoint = rtrim($settings['stregsystem_api_endpoint'] ?? 'https://stregsystem.fklub.dk', '/');
    $room_id  = $settings['stregsystem_room_id'] ?? '10';

    if (empty($endpoint)) {
        return null;
    }

    $api_url = add_query_arg('room_id', $room_id, $endpoint . '/api/products/active_products');

    $response = wp_remote_get($api_url, [
        'timeout' => 10,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $products = json_decode($body, true);

    if (!is_array($products)) {
        return null;
    }

    // Cache for 10 minutes
    set_transient($transient_key, $products, 10 * MINUTE_IN_SECONDS);
    return $products;
}

/**
 * Check Stregsystem validation status (ID existence, name, and price) for a WooCommerce product.
 *
 * Note: Stregsystem prices are given in cents / streg-ører (e.g. 150 = 1.50).
 *
 * @param int $product_id
 * @param string|null $streg_id Optional override ID (e.g. during save).
 * @return array Status payload.
 */
function stregpay_check_product_status($product_id, $streg_id = null) {
    if ($streg_id === null) {
        $streg_id = get_post_meta($product_id, '_stregsystem_product_id', true);
    }

    $streg_id = trim((string) $streg_id);

    if ($streg_id === '') {
        return ['status' => 'empty', 'id' => '', 'message' => __('Not linked', 'stregpay-checkout')];
    }

    $active_products = stregpay_get_active_products();

    if ($active_products === null) {
        return [
            'status'  => 'offline',
            'id'      => $streg_id,
            'message' => __('Stregsystem API is unreachable', 'stregpay-checkout'),
        ];
    }

    if (!isset($active_products[$streg_id])) {
        return [
            'status'  => 'not_found',
            'id'      => $streg_id,
            'message' => sprintf(__('ID %s not found in active Stregsystem products', 'stregpay-checkout'), $streg_id),
        ];
    }

    $streg_item     = $active_products[$streg_id];
    $streg_name_raw = $streg_item['name'] ?? '';
    $streg_name     = stregpay_normalize_product_name($streg_name_raw);
    $wc_name        = get_the_title($product_id);
    $wc_name_norm   = stregpay_normalize_product_name($wc_name);
    $name_matches   = (strcasecmp($wc_name_norm, $streg_name) === 0);

    // Stregsystem price is given in cents (e.g. 150 = 1.50)
    $streg_price_cents = isset($streg_item['price']) ? (int) $streg_item['price'] : null;
    $streg_price_major = ($streg_price_cents !== null) ? ($streg_price_cents / 100) : null;

    $product        = wc_get_product($product_id);
    $wc_price_raw   = $product ? $product->get_price() : '';
    $has_wc_price   = ($wc_price_raw !== '' && $wc_price_raw !== null);
    $wc_price_cents = $has_wc_price ? (int) round((float) $wc_price_raw * 100) : null;
    $wc_price_major = $has_wc_price ? (float) $wc_price_raw : null;

    $price_matches = ($streg_price_cents !== null && $has_wc_price && $streg_price_cents === $wc_price_cents);
    $is_match      = ($name_matches && $price_matches);

    $mismatches = [];
    if (!$name_matches) {
        $mismatches[] = 'name';
    }
    if (!$price_matches) {
        $mismatches[] = 'price';
    }

    $streg_price_formatted = stregpay_format_price($streg_price_major);
    $wc_price_formatted    = $has_wc_price ? stregpay_format_price($wc_price_major) : __('no price set', 'stregpay-checkout');

    return [
        'status'                => $is_match ? 'match' : 'mismatch',
        'id'                    => $streg_id,
        'streg_name'            => $streg_name,
        'wc_name'               => $wc_name,
        'streg_price_cents'     => $streg_price_cents,
        'streg_price_major'     => $streg_price_major,
        'streg_price_formatted' => $streg_price_formatted,
        'wc_price_major'        => $wc_price_major,
        'wc_price_formatted'    => $wc_price_formatted,
        'name_matches'          => $name_matches,
        'price_matches'         => $price_matches,
        'mismatches'            => $mismatches,
    ];
}

// Add field to General tab
add_action('woocommerce_product_options_general_product_data', function () {
    global $post;
    $status_html = '';

    if ($post && $post->ID) {
        $check = stregpay_check_product_status($post->ID);

        switch ($check['status']) {
            case 'match':
                $status_html = '<span style="color: #008a20; font-weight: 600;">&#10003; ' . sprintf(
                    esc_html__('Stregsystem: Linked to "%s" (%s)', 'stregpay-checkout'),
                    esc_html($check['streg_name']),
                    esc_html($check['streg_price_formatted'])
                ) . '</span>';
                break;

            case 'mismatch':
                if (!$check['name_matches'] && !$check['price_matches']) {
                    $status_html = '<span style="color: #dba617; font-weight: 600;">&#9888; ' . sprintf(
                        esc_html__('Stregsystem mismatch: Stregsystem is "%s" (%s), but WooCommerce is "%s" (%s)', 'stregpay-checkout'),
                        esc_html($check['streg_name']),
                        esc_html($check['streg_price_formatted']),
                        esc_html($check['wc_name']),
                        esc_html($check['wc_price_formatted'])
                    ) . '</span>';
                } elseif (!$check['name_matches']) {
                    $status_html = '<span style="color: #dba617; font-weight: 600;">&#9888; ' . sprintf(
                        esc_html__('Stregsystem name mismatch: Stregsystem has "%s", WooCommerce has "%s" (price matches: %s)', 'stregpay-checkout'),
                        esc_html($check['streg_name']),
                        esc_html($check['wc_name']),
                        esc_html($check['streg_price_formatted'])
                    ) . '</span>';
                } else {
                    $status_html = '<span style="color: #dba617; font-weight: 600;">&#9888; ' . sprintf(
                        esc_html__('Stregsystem price mismatch: Stregsystem is %s, WooCommerce is %s', 'stregpay-checkout'),
                        esc_html($check['streg_price_formatted']),
                        esc_html($check['wc_price_formatted'])
                    ) . '</span>';
                }
                break;

            case 'not_found':
                $status_html = '<span style="color: #d63638; font-weight: 600;">&#10007; ' . sprintf(
                    esc_html__('Warning: Stregsystem ID "%s" not found among active products.', 'stregpay-checkout'),
                    esc_html($check['id'])
                ) . '</span>';
                break;

            case 'offline':
                $status_html = '<span style="color: #8c8f94;">&#9888; ' . esc_html__('Stregsystem API is currently unreachable.', 'stregpay-checkout') . '</span>';
                break;
        }
    }

    $description = __('ID of the product in the Stregsystem.', 'stregpay-checkout');
    if (!empty($status_html)) {
        $description .= '<br>' . $status_html;
    }

    woocommerce_wp_text_input([
        'id'          => '_stregsystem_product_id',
        'label'       => __('Stregsystem Product ID', 'stregpay-checkout'),
        'desc_tip'    => empty($status_html),
        'description' => $description,
    ]);
});

// Save field & run on-save validation
add_action('woocommerce_process_product_meta', function ($product_id) {
    if (!isset($_POST['_stregsystem_product_id'])) {
        return;
    }

    $streg_id = sanitize_text_field(wp_unslash($_POST['_stregsystem_product_id']));
    update_post_meta($product_id, '_stregsystem_product_id', $streg_id);

    if ($streg_id === '') {
        return;
    }

    // If active products cache doesn't contain this ID, do a fresh fetch in case it was just added in Stregsystem
    $cached = get_transient('stregpay_active_products');
    if (is_array($cached) && !isset($cached[$streg_id])) {
        stregpay_get_active_products(true);
    }

    $check = stregpay_check_product_status($product_id, $streg_id);

    if ($check['status'] === 'not_found') {
        $settings = get_option('woocommerce_stregpay_settings', []);
        $room_id  = $settings['stregsystem_room_id'] ?? '10';
        set_transient("stregpay_notice_{$product_id}", [
            'type'    => 'error',
            'message' => sprintf(
                __('Warning: Stregsystem ID "%s" was not found among active products in room %s.', 'stregpay-checkout'),
                $streg_id,
                $room_id
            ),
        ], 45);
    } elseif ($check['status'] === 'mismatch') {
        if (!$check['name_matches'] && !$check['price_matches']) {
            $msg = sprintf(
                __('Name and price mismatch: Stregsystem product #%s is "%s" (%s), but WooCommerce has "%s" (%s).', 'stregpay-checkout'),
                $check['id'],
                $check['streg_name'],
                $check['streg_price_formatted'],
                $check['wc_name'],
                $check['wc_price_formatted']
            );
        } elseif (!$check['name_matches']) {
            $msg = sprintf(
                __('Product name mismatch: WooCommerce title is "%s", but Stregsystem product #%s is named "%s" (price matches: %s).', 'stregpay-checkout'),
                $check['wc_name'],
                $check['id'],
                $check['streg_name'],
                $check['streg_price_formatted']
            );
        } else {
            $msg = sprintf(
                __('Product price mismatch: WooCommerce price is %s, but Stregsystem product #%s is %s.', 'stregpay-checkout'),
                $check['wc_price_formatted'],
                $check['id'],
                $check['streg_price_formatted']
            );
        }
        set_transient("stregpay_notice_{$product_id}", [
            'type'    => 'warning',
            'message' => $msg,
        ], 45);
    } elseif ($check['status'] === 'offline') {
        set_transient("stregpay_notice_{$product_id}", [
            'type'    => 'warning',
            'message' => sprintf(
                __('Stregsystem Product ID %s was saved, but the Stregsystem API could not be reached to verify it.', 'stregpay-checkout'),
                $streg_id
            ),
        ], 45);
    } elseif ($check['status'] === 'match') {
        set_transient("stregpay_notice_{$product_id}", [
            'type'    => 'success',
            'message' => sprintf(
                __('Stregsystem ID %s verified successfully (matches "%s", %s).', 'stregpay-checkout'),
                $streg_id,
                $check['streg_name'],
                $check['streg_price_formatted']
            ),
        ], 45);
    }
});

// Display transient notice on product edit screen
add_action('admin_notices', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'product') {
        return;
    }

    global $post;
    if (!$post) {
        return;
    }

    $notice = get_transient("stregpay_notice_{$post->ID}");
    if ($notice) {
        delete_transient("stregpay_notice_{$post->ID}");
        printf(
            '<div class="notice notice-%s is-dismissible"><p><strong>%s:</strong> %s</p></div>',
            esc_attr($notice['type']),
            esc_html__('Stregpay', 'stregpay-checkout'),
            esc_html($notice['message'])
        );
    }
});

// Add Stregsystem column to WooCommerce products table
add_filter('manage_edit-product_columns', function ($columns) {
    if (isset($_GET['refresh_stregsystem']) && current_user_can('manage_woocommerce')) {
        delete_transient('stregpay_active_products');
    }

    $refresh_url = add_query_arg('refresh_stregsystem', '1');
    $header_title = sprintf(
        '%s <a href="%s" title="%s" style="text-decoration: none; color: inherit;"><span class="dashicons dashicons-update" style="font-size: 14px; vertical-align: middle;"></span></a>',
        esc_html__('Stregsystem', 'stregpay-checkout'),
        esc_url($refresh_url),
        esc_attr__('Refresh Stregsystem cache', 'stregpay-checkout')
    );

    $new_columns = [];
    foreach ($columns as $key => $title) {
        $new_columns[$key] = $title;
        if ($key === 'name') {
            $new_columns['stregsystem_status'] = $header_title;
        }
    }
    if (!isset($new_columns['stregsystem_status'])) {
        $new_columns['stregsystem_status'] = $header_title;
    }
    return $new_columns;
});

// Render Stregsystem column content in products table
add_action('manage_product_posts_custom_column', function ($column, $post_id) {
    if ($column !== 'stregsystem_status') {
        return;
    }

    $check = stregpay_check_product_status($post_id);

    switch ($check['status']) {
        case 'empty':
            echo '<span class="na" style="color: #a7aaad;">&mdash;</span>';
            break;

        case 'offline':
            printf(
                '<span title="%s" style="color: #8c8f94;"><span class="dashicons dashicons-warning" style="font-size: 16px; vertical-align: text-top; line-height: 1.2;"></span> #%s <small>(offline)</small></span>',
                esc_attr__('Stregsystem API is unreachable', 'stregpay-checkout'),
                esc_html($check['id'])
            );
            break;

        case 'not_found':
            printf(
                '<span title="%s" style="color: #d63638; font-weight: 500;"><span class="dashicons dashicons-dismiss" style="font-size: 16px; vertical-align: text-top; line-height: 1.2;"></span> #%s <small>(%s)</small></span>',
                esc_attr(sprintf(__('ID %s not found in active Stregsystem products', 'stregpay-checkout'), $check['id'])),
                esc_html($check['id']),
                esc_html__('Not found', 'stregpay-checkout')
            );
            break;

        case 'match':
            printf(
                '<span title="%s" style="color: #008a20; font-weight: 500;"><span class="dashicons dashicons-yes-alt" style="font-size: 16px; vertical-align: text-top; line-height: 1.2;"></span> #%s <small>(%s &bull; %s)</small></span>',
                esc_attr(sprintf(__('Matches: %s (%s)', 'stregpay-checkout'), $check['streg_name'], $check['streg_price_formatted'])),
                esc_html($check['id']),
                esc_html($check['streg_name']),
                esc_html($check['streg_price_formatted'])
            );
            break;

        case 'mismatch':
            if (!$check['name_matches'] && !$check['price_matches']) {
                $subtext = sprintf('%s &bull; %s', $check['streg_name'], $check['streg_price_formatted']);
                $tip     = sprintf(
                    __('Mismatch. Stregsystem: "%s" (%s) | WooCommerce: "%s" (%s)', 'stregpay-checkout'),
                    $check['streg_name'],
                    $check['streg_price_formatted'],
                    $check['wc_name'],
                    $check['wc_price_formatted']
                );
            } elseif (!$check['name_matches']) {
                $subtext = sprintf('Name: %s', $check['streg_name']);
                $tip     = sprintf(
                    __('Name mismatch. Stregsystem: "%s" | WooCommerce: "%s" (Price: %s)', 'stregpay-checkout'),
                    $check['streg_name'],
                    $check['wc_name'],
                    $check['streg_price_formatted']
                );
            } else {
                $subtext = sprintf('Price: %s vs %s', $check['streg_price_formatted'], $check['wc_price_formatted']);
                $tip     = sprintf(
                    __('Price mismatch. Stregsystem: %s | WooCommerce: %s', 'stregpay-checkout'),
                    $check['streg_price_formatted'],
                    $check['wc_price_formatted']
                );
            }

            printf(
                '<span title="%s" style="color: #dba617; font-weight: 500;"><span class="dashicons dashicons-warning" style="font-size: 16px; vertical-align: text-top; line-height: 1.2;"></span> #%s <small>(%s)</small></span>',
                esc_attr($tip),
                esc_html($check['id']),
                esc_html($subtext)
            );
            break;
    }
}, 10, 2);

// Adjust column width in admin table
add_action('admin_head', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->id === 'edit-product') {
        echo '<style>.manage-column.column-stregsystem_status { width: 14%; }</style>';
    }
});

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
