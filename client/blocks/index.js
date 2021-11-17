/**
 * External dependencies
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import $ from 'jquery';

const getKCOServerData = () => {
	const kcoServerData = getSetting( 'kco_data', null );
	if ( ! kcoServerData ) {
		throw new Error(
			__(
				'KCO initialization data is not available',
				'klarna-checkout-for-woocommerce'
			)
		);
	}
	return kcoServerData;
};

const {
	snippetKCO,
	payForOrder,
	getKlarnaOrderUrl,
	getKlarnaOrderNonce,
} = getKCOServerData();

let callbackFlag = null;

const renderCheckoutSnippet = () => {
	const checkoutContainer = document.querySelector( '#kco-iframe' );
	if ( checkoutContainer ) {
		checkoutContainer.innerHTML = snippetKCO;
		// This is necessary otherwise the scripts tags are not going to be evaluated
		checkoutContainer.querySelectorAll( 'script' ).forEach( ( script ) => {
			const parentNode = script.parentNode;
			const newScript = document.createElement( 'script' );
			newScript.type = 'text/javascript';
			newScript.text = script.text;
			parentNode.removeChild( script );
			parentNode.appendChild( newScript );
		} );
		return true;
	}
	return false;
};

const placeKlarnaOrder = ( callback ) => {
	getKlarnaOrder().done( ( response ) => {
		if ( response.success ) {
			// get wc button and submit the form
			const btn = document.querySelector(
				'.components-button.wc-block-components-button.wc-block-components-checkout-place-order-button'
			);

			callbackFlag = callback;

			//submit the form
			btn.click();

			callback( { should_proceed: true } );
		}
	} );
};
const PaymentMethod = ( props ) => {
	const {
		eventRegistration,
		billing,
		shippingData,
		method,
		onSubmit,
		checkoutStatus,
	} = props;
	const {
		onCheckoutAfterProcessingWithSuccess,
		onCheckoutAfterProcessingWithError,
	} = eventRegistration;
	const [ isSetOnCheckoutSuccess, setIsSetOnCheckoutSuccess ] = useState(
		false
	);
	const [ isSetOnCheckoutError, setIsSetOnCheckoutError ] = useState( false );
	const [ isKCOLoaded, setIsKCOLoaded ] = useState( false );

	const { isIdle, isComplete, isProcessing, isCalculating } = checkoutStatus;

	useEffect( () => {
		if ( ! isKCOLoaded ) {
			const loaded = renderCheckoutSnippet();
			if ( loaded === true ) {
				setIsKCOLoaded( loaded );
			}
		}
	}, [ isKCOLoaded ] );
	useEffect( () => {
		if ( ! isSetOnCheckoutSuccess ) {
			onCheckoutAfterProcessingWithError(
				( processingResponseErrData ) => {
					console.error( '45453', processingResponseErrData );
				}
			);
			setIsSetOnCheckoutError( true );
		}
	}, [ isSetOnCheckoutError ] );

	useEffect( () => {
		if ( ! isSetOnCheckoutSuccess ) {
			onCheckoutAfterProcessingWithSuccess(
				( processingResponseData ) => {
					const {
						orderId,
						processingResponse,
					} = processingResponseData;

					const {
						paymentDetails,
						paymentStatus,
					} = processingResponse;
					callbackFlag( { should_proceed: true } );
				}
			);
			setIsSetOnCheckoutSuccess( true );
		}
	}, [ isSetOnCheckoutSuccess, callbackFlag ] );
	if ( typeof window._klarnaCheckout === 'function' ) {
		window._klarnaCheckout( function ( api ) {
			api.on( {
				validation_callback( data, callback ) {
					if ( payForOrder ) {
						callback( { should_proceed: true } );
					} else {
						placeKlarnaOrder( callback );
					}
				},
			} );
		} );
	}
	return <div className="kco-test-class" id="kco-iframe" />;
};

const getKlarnaOrder = ( callback ) => {
	return $.ajax( {
		type: 'POST',
		url: getKlarnaOrderUrl,
		data: {
			nonce: getKlarnaOrderNonce,
		},
		dataType: 'json',
		success( data ) {
			// Check Terms checkbox, if it exists.
			if ( $( 'form.checkout #terms' ).length > 0 ) {
				$( 'form.checkout #terms' ).prop( 'checked', true );
			}
		},
		error( data ) {
			console.log( 'error' );
		},
		complete( data ) {},
	} );
};

const options = {
	name: 'kco',
	content: <PaymentMethod />,
	label: <strong>Klarna</strong>,
	ariaLabel: 'kco',
	edit: <PaymentMethod />,
	canMakePayment: () => true,
	paymentMethodId: 'kco',
	supports: {
		features: [ 'products' ],
	},
};

registerPaymentMethod( options );
