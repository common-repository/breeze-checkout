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
 * Class B1CCO_Hooks.
 */
class B1CCO_Hooks {

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
	}

	// Activation hook callback
	public function on_activate() {
		// error_log('My WooCommerce Plugin: Plugin activated at ' . current_time('mysql'));
		$shop_id = $this->get_shop_id();
		$this->call_api( $shop_id, 'activated' );
	}

	// Deactivation hook callback
	public function on_deactivate() {
		// error_log('My WooCommerce Plugin: Plugin deactivated at ' . current_time('mysql'));
		$shop_id = $this->get_shop_id();
		$this->call_api( $shop_id, 'deactivated' );
	}

	// Retrieve the shop ID (adjust this to your actual method of getting the shop ID)
	public function get_shop_id() {
		$shop_id = get_option( 'woocommerce_shop_id' );
		if ( ! $shop_id ) {
			$shop_id = '<shop_id>'; // Fallback if no shop ID is found
		}
		return $shop_id;
	}

	// Call the external API with given shop ID and status
	public function call_api( $shop_id, $status ) {
		$api_url = B1CCO_PLUGIN_STATUS_HOOK_URL;
		$api_url = add_query_arg(
			array(
				'shopid' => urlencode( $shop_id ),
				'status' => urlencode( $status ),
			),
			$api_url
		);

		$response = wp_remote_get( $api_url );

		if ( is_wp_error( $response ) ) {
			error_log( 'API request failed: ' . $response->get_error_message() );
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code != 200 ) {
			error_log( 'API request returned an error: HTTP ' . $response_code );
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		error_log( 'API request successful: ' . $body );
	}
}

// Initialize the plugin
B1CCO_Hooks::get_instance();
