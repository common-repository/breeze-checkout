<?php
/**
 * Remove Native Buttons
 *
 * @package Breeze 1-Click Checkout
 */

namespace B1CCO\Admin\Inc;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class B1CCO_Remove_Native_Buttons.
 */
class B1CCO_Remove_Native_Buttons {

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

	/**
	 * Constructor
	 */
	public function __construct() {
		remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 0 );
		remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_proceed_to_checkout', 0 );
		remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_proceed_to_checkout', 0 );
		remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_button_view_cart', 0 );
	}
}

/**
 *  Prepare if class 'B1CCO_Remove_Native_Buttons' exist.
 *  Kicking this off by calling 'get_instance()' method
 */
B1CCO_Remove_Native_Buttons::get_instance();
