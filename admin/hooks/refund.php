<?php
/**
 * Add Breeze Buttons
 *
 * @package Breeze 1-Click Checkout
 */

namespace B1CCO\Admin\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class B1CCO_Order_Refund.
 */
class B1CCO_Order_Refund {

	/**
	 * Member Variable
	 *
	 * @var instance
	 */
	private static $instance;

	/**
	 *  Initiator
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// Constructor to initialize the plugin
	private function __construct() {
		add_action( 'woocommerce_order_refunded', array( $this, 'order_refunded_hook' ), 0 );
	}

	// Call the external API with given shop ID and status
	public function order_refunded_hook( $order_id ) {
		$enable = get_option( 'b1cco_btn_enable_order_refund', false );

		if ( $enable ) {
			$api_url  = B1CCO_ORDER_REFUND_WEBHOOK_URL;
			$sign_key = B1CCO_REFUND_WEBHOOK_SIGN_KEY;

			// Retrieve the WooCommerce order
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				error_log( 'Order not found: ' . $order_id );
				return;
			}

			// Check if the order has the 'breezeCartId' meta
			if ( ! $order->meta_exists( 'breezeCartId' ) ) {
				error_log( 'Order does not contain breezeCartId: ' . $order_id );
				return;
			}

			$order_data = $this->fetch_order_data( $order );

			// Convert request payload to JSON string
			$json_payload = wp_json_encode( $order_data );

			// Generate HMAC-SHA256 signature
			$hmac             = hash_hmac( 'sha256', $json_payload, $sign_key, true );
			$base64_signature = base64_encode( $hmac );

			// Send POST request to the API
			$response = wp_remote_post(
				$api_url,
				array(
					'method'  => 'POST',
					'body'    => $json_payload,
					'headers' => array(
						'Content-Type'              => 'application/json',
						'x-woocommerce-hmac-sha256' => $base64_signature,
					),
				)
			);

			// Handle the response and errors
			if ( is_wp_error( $response ) ) {
				error_log( 'Breeze:Webhook:Refund - API request failed: ' . $response->get_error_message() );
				return;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code != 200 ) {
				error_log( 'Breeze:Webhook:Refund - API request returned an error: HTTP ' . $response_code );
				return;
			}
			$body = wp_remote_retrieve_body( $response );
			error_log( 'API request successful: ' . $body );
		}
	}

	public function fetch_order_data( $order ) {
		// Check if the order object or payment method is null
		// if ($order === null || $order->get_payment_method() === 'cod') {
		// return null; // Exit if order is null or payment method is 'cod'
		// }

		// Initialize amount to null
		$amount = null;

		// Check fee lines for 'Partial Paid' and handle potential null values
		$fee_lines = $order->get_fees();
		if ( ! empty( $fee_lines ) ) {
			foreach ( $fee_lines as $fee ) {
				if ( $fee !== null && strpos( strtolower( $fee->get_name() ), 'partial' ) !== false ) {
					// Check for null on fee amount and tax before summing
					$fee_amount = $fee->get_amount() !== null ? $fee->get_amount() : 0;
					$fee_tax    = $fee->get_total_tax() !== null ? $fee->get_total_tax() : 0;
					$amount     = strval($fee_amount + $fee_tax);
          $amount     = !empty($amount) ? ltrim($amount, '-') : '0';
					break;
				}
			}
		}

		// If no relevant fee found, check refund total (handle null refunds)
		if ( $amount === null ) {
			$refunds = $order->get_refunds();
			if ( ! empty( $refunds ) && isset( $refunds[0] ) ) {
				$refund_total = $refunds[0]->get_total() !== null ? ltrim( $refunds[0]->get_total(), '-' ) : '0';
				$amount       = $refund_total;
			} else {
				$amount = $order->get_total() !== null ? $order->get_total() : 0; // Default to order total, with null check
			}
		}

		// Retrieve the 'breezeOrderId' from meta data, with null checks
		$breezeOrderId = null;
		$meta_data     = $order->get_meta_data();
		if ( ! empty( $meta_data ) ) {
			foreach ( $meta_data as $meta ) {
				if ( $meta !== null && $meta->key === 'breezeOrderId' ) {
					$breezeOrderId = $meta->value !== null ? $meta->value : null;
					break;
				}
			}
		}

		// Check if order ID is null
		$order_id = $order->get_id() !== null ? $order->get_id() : null;

		// Prepare the order data with null-safe values
		$order_data = array(
			'refund_amount'   => $amount !== null ? $amount : '0',
			'breeze_order_id' => $breezeOrderId,
			'id'              => strval( $order_id ),
		);

		return $order_data;
	}

	public static function get_data( $obj ) {
		$response = array();
		foreach ( $obj as $obj_item ) {
			array_push( $response, $obj_item->get_data() );
		}

		return $response;
	}
}

// Initialize the plugin
B1CCO_Order_Refund::get_instance();
