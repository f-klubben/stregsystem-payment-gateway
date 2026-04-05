/**
 * External dependencies
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import { createElement } from '@wordpress/element';

// Stregpay logo component
const StregpayLogo = () => (
	<div
		style={ {
			display: 'flex',
			alignItems: 'center',
			gap: '8px',
			fontWeight: '600',
			color: '#2c3e50',
		} }
	>
		<svg
			width="24"
			height="24"
			viewBox="0 0 24 24"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
		>
			<rect x="2" y="2" width="20" height="20" rx="4" fill="#4285F4" />
			<path d="M8 12L10.5 8.5L16 12L10.5 15.5L8 12Z" fill="white" />
		</svg>
		<span>Stregpay</span>
	</div>
);

// Simple placeholder component
const PlaceholderComponent = () =>
	createElement(
		'div',
		null,
		__( 'Stregpay payment method', 'stregpay-checkout' )
	);

// Register Stregpay payment method
const StregpayPaymentMethod = {
	name: 'stregpay',
	label: <StregpayLogo />,
	content: createElement( PlaceholderComponent ),
	edit: createElement( PlaceholderComponent ),
	canMakePayment: () => true,
	ariaLabel: __( 'Stregpay', 'stregpay-checkout' ),
	paymentMethodId: 'stregpay',
	icon: <StregpayLogo />,
	supports: {
		features: [ 'products' ],
	},
};

// Register the payment method
if ( typeof registerPaymentMethod === 'function' ) {
	registerPaymentMethod( StregpayPaymentMethod );
}
