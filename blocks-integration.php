<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry;

define ( 'StregpayCheckout_VERSION', '0.1.0' );

/**
 * Class for integrating Stregpay with WooCommerce Blocks
 */
class StregpayCheckout_Blocks_Integration extends AbstractPaymentMethodType {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'stregpay';

	/**
	 * An instance of the Asset Data Registry
	 *
	 * @var AssetDataRegistry
	 */
	private $asset_data_registry;

	/**
	 * Constructor
	 *
	 * @param AssetDataRegistry $asset_data_registry An instance of AssetDataRegistry.
	 */
	public function __construct( AssetDataRegistry $asset_data_registry ) {
		$this->asset_data_registry = $asset_data_registry;
	}

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_stregpay_settings', [] );
		// Debug: Log settings
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('STREGPAY BLOCKS: Settings loaded: ' . print_r($this->settings, true));
		}
	}

	/**
	 * Returns if this payment method should be active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		// Get the enabled setting directly since get_setting is protected
		$enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : false;
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('STREGPAY BLOCKS: is_active() called, enabled=' . $enabled);
		}
		return filter_var( $enabled, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'blocks-integration',
			plugins_url( '/build/index.js', __FILE__ ),
			$this->get_file_dependencies( '/build/index.asset.php' ),
			$this->get_file_version( '/build/index.js' ),
			true
		);

		return [ 'blocks-integration' ];
	}

	/**
	 * Returns an array of key=>value pairs of data to be passed to the payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
			'stregpay-api-endpoint' => rest_url('stregpay/v1'),
			'stregpay-nonce' => wp_create_nonce('wp_rest'),
		];
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		return [ 'products' ];
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( $file ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( dirname( __FILE__ ) . $file ) ) {
			return filemtime( dirname( __FILE__ ) . $file );
		}
		return StregpayCheckout_VERSION;
	}

	/**
	 * Get file dependencies from asset file.
	 *
	 * @param string $file Local path to the asset file.
	 * @return array
	 */
	protected function get_file_dependencies( $file ) {
		$asset_path = dirname( __FILE__ ) . $file;
		if ( file_exists( $asset_path ) ) {
			$asset_data = require $asset_path;
			return isset( $asset_data['dependencies'] ) ? $asset_data['dependencies'] : [];
		}
		return [];
	}
}
