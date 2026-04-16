<?php
/**
 * Stregpay Payment Method for WooCommerce Blocks
 * 
 * Modern block-based payment method implementation
 */

class WC_Stregpay_Payment_Method extends WC_Payment_Gateway {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'stregpay';
        $this->method_title = __('Stregpay', 'stregpay-checkout');
        $this->method_description = __('Pay with Stregpay club points (streger)', 'stregpay-checkout');
        $this->supports = array(
            'products',
            'refunds'
        );

        $this->title = 'StregPay';
        $this->description = 'Pay with StregPay';

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->enabled = $this->get_option('enabled');
        $this->settings = [
            'api_endpoint' => $this->get_option('stregsystem_api_endpoint'),
            'room_id' => $this->get_option('stregsystem_room_id'),
        ];

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Debug: Log that constructor completed successfully
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('STREGPAY: Payment method constructor completed successfully');
            error_log('STREGPAY: Enabled: ' . ($this->enabled === 'yes' ? 'YES' : 'NO'));
        }
    }

    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'stregpay-checkout'),
                'type' => 'checkbox',
                'label' => __('Enable Stregpay', 'stregpay-checkout'),
                'default' => 'yes'
            ),
            'stregsystem_room_id' => array(
                'title' => 'Stregsystem Room ID',
                'type' => 'text',
                'default' => '10',
                'desc_tip' => true,
            ),
			'stregsystem_api_endpoint' => array(
				'title' => 'Stregsystem API Endpoint',
				'type' => 'text',
				'default' => 'https://stregsystem.fklub.dk',
				'desc_tip' => true,
			),
        );
    }

    /**
     * Check if payment method is available
     *
     * @return bool
     */
    public function is_available() {
        $is_available = parent::is_available();

        return $is_available && $this->enabled === 'yes';
    }

    /**
     * Process the payment
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        // Set order status to on-hold (awaiting payment)
        $order->update_status('on-hold', __('Awaiting Stregpay payment', 'stregpay-checkout'));
        
        // Reduce stock levels
        wc_reduce_stock_levels($order_id);
        
        // Create payment intent (this would be your API call)
        $payment_intent_url = $this->create_payment_intent($order);
        
        // Return success and redirect to payment intent URL
        return array(
            'result' => 'success',
            'redirect' => $payment_intent_url
        );
    }

    /**
     * Create payment intent - calls Stregpay API /api/sale/intent endpoint
     *
     * @param WC_Order $order
     * @return string Payment intent URL (confirmation_url from API response)
     */
    private function create_payment_intent($order) {
        // Mock product string
        $product_string = 'øl:3';

        // Get room ID from order meta or use default
        $room_id = $this->settings['room_id'];

        // Prepare API request according to api.yaml specification
        $api_url = $this->settings['api_endpoint'] . '/api/sale/intent';

        $request_body = [
            'productstring' => $product_string,
            'room_id' => $room_id,
            'webhook_url' => home_url('/stregpay/v1/webhook'),
            'return_url' => $order->get_checkout_order_received_url(),
            'max_expires_in_seconds' => 600     // 10 minutes
        ];

        // Log request for debugging
		error_log('STREGPAY: Creating payment intent for order ' . $order->get_id());
		error_log('STREGPAY: Request body: ' . print_r($request_body, true));

        // Check if API endpoint is configured
        if (empty($this->settings['api_endpoint'])) {
            error_log('STREGPAY: API endpoint not configured');
            throw new Exception(__('Stregsystem API endpoint not configured. Please contact site administrator.', 'stregpay-checkout'));
        }

        // Call Stregpay API /api/sale/intent endpoint
        $response = wp_remote_post($api_url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($request_body),
            'timeout' => 30,
            'data_format' => 'body'
        ]);

        // Handle API response
        if (is_wp_error($response)) {
            error_log('STREGPAY API Error: ' . $response->get_error_message());
			throw new Exception(__('Unable to connect to Stregsystem. Please try again.', 'stregpay-checkout'));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        // Log response for debugging
		error_log('STREGPAY: API Response Code: ' . $response_code);
		error_log('STREGPAY: API Response Body: ' . print_r($response_body, true));

        // Check for successful response (201 Created per api.yaml)
        if ($response_code === 201) {
            if (!empty($response_body['confirmation_url'])) {
                // Store intent ID in order meta for webhook handling
                if (!empty($response_body['id'])) {
                    $order->update_meta_data('_stregsystem_intent_id', $response_body['id']);
                    $order->save();
                }
                return $response_body['confirmation_url'];
            } else {
                throw new Exception(__('Invalid response from Stregsystem: missing confirmation_url', 'stregpay-checkout'));
            }
        } else {
            // Handle specific error cases from api.yaml
            $error_message = $response_body['detail'] ?? $response_body['message'] ?? $response_body['error'] ?? 'Unknown error';
            error_log('STREGPAY API Error: ' . $error_message);

            if ($response_code === 400) {
                // Bad request - invalid parameters
                throw new Exception(__('Invalid Stregsystem request: ', 'stregpay-checkout') . $error_message);
            } else {
                throw new Exception(__('Stregsystem error: ', 'stregpay-checkout') . $error_message);
            }
        }
    }

    /**
     * Output field for the payment method
     */
    public function payment_fields() {
        // For traditional checkout
        echo '<p>' . esc_html($this->description) . '</p>';
        echo '<field set="stregpay-fields">';
        echo '</field set>';
    }
    
    /**
     * Get the payment method icon
     *
     * @return string
     */
    public function get_icon() {
        // Modern Stregpay logo SVG
        $icon = '<svg width="40" height="24" viewBox="0 0 40 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px;">
            <rect x="2" y="2" width="36" height="20" rx="4" fill="#4285F4"/>
            <path d="M12 12L16 7L24 12L16 17L12 12Z" fill="white"/>
        </svg>
        <span style="font-weight: 600; color: #2c3e50; vertical-align: middle;">Stregpay</span>';
        
        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }
}