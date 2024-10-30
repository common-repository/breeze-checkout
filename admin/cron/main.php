<?php
/**
 * Add Breeze Buttons
 *
 * @package Breeze 1-Click Checkout
 */

namespace B1CCO\Admin\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class B1CCO_Cron.
 */
class B1CCO_Cron {

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
		add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );

		// Check the global setting before scheduling the cron job
		if ( $this->is_cron_enabled( 'b1cco_btn_enable_custom_cron_jobs' ) ) {
			add_action( 'init', array( $this, 'schedule_cron_job' ) );
			add_action( 'create_wc_order_for_partially_paid_order', array( $this, 'handle_cron_job' ) );
		}
	}

	private function is_cron_enabled( $option ) {
		return get_option( $option, false );
	}

	public function add_cron_intervals( $schedules ) {
		$schedules['five_minutes'] = array(
			'interval' => 5 * 60,
			'display'  => __( 'Every 5 Minutes', 'breeze-checkout' ),
		);

		return $schedules;
	}

	public function schedule_cron_job() {
		if ( $this->is_cron_enabled( 'b1cco_btn_enable_cron_job_for_partially_paid_order_creation' ) && ! wp_next_scheduled( 'create_wc_order_for_partially_paid_order' ) ) {
			wp_schedule_event( time(), 'five_minutes', 'create_wc_order_for_partially_paid_order' );
		}
	}

	public function handle_cron_job() {
		if ( $this->is_cron_enabled( 'b1cco_btn_enable_cron_job_for_partially_paid_order_creation' ) ) {
			$this->handle_create_wc_order_for_partially_paid_order();
		}
	}

	public function handle_create_wc_order_for_partially_paid_order() {
		global $wpdb;
		// Log the start of the function execution
		error_log( 'Cron job started: create_wc_order_for_partially_paid_order' );

		// Get the current time and the time 5 minutes ago
		$current_time       = current_time( 'timestamp' );
		$time_5_minutes_ago = $current_time - ( 30 * 60 );

		// Convert timestamps to WooCommerce's expected datetime format
		$date_from = gmdate( 'Y-m-d H:i:s', $time_5_minutes_ago );
		// $date_to = date('Y-m-d H:i:s', $current_time);

		// Query to get completed orders from the past 5 minutes
		$args = array(
			'status'       => 'completed',
			'date_created' => '>=' . $date_from,
			'limit'        => -1,
		);

		$orders = wc_get_orders( $args );

		// Log the number of orders found
		error_log( 'Number of completed orders found: ' . count( $orders ) );

		// Iterate through each order to find the ones with "PREPAID" in meta_data
		foreach ( $orders as $order ) {
			// Check if the order has already been processed by checking for the presence of a specific meta data key
			$processed = $order->get_meta( 'processed_by_custom_cron_job' );

			if ( $processed ) {
				error_log( 'Order ID ' . $order->get_id() . ' has already been processed by the custom cron job.' );
				continue; // Skip this order and move to the next one
			}

			$prepaid_value    = null;
			$prepaid_fee_line = null;

			// Retrieve fee lines and check for "PREPAID" name
			foreach ( $order->get_fees() as $fee ) {
				if ( $fee->get_name() === 'PREPAID' ) {
					$fee_total     = $fee->get_total();
					$fee_total_tax = $fee->get_total_tax();
					$prepaid_value = abs( $fee_total ) + abs( $fee_total_tax );
					break; // Found the "PREPAID" fee line, no need to check further
				}
			}
			error_log( 'prepaid_value: ' . $prepaid_value );

			// Log the "PREPAID" value if found
			if ( $prepaid_value !== null ) {
				error_log( 'Order ID ' . $order->get_id() . ' has PREPAID value: ' . $prepaid_value );
			}

			// If "PREPAID" key is found and is a negative value, proceed to create a new order
			if ( $prepaid_value !== null && $prepaid_value > 0 ) {
				// Initialize an empty WooCommerce order
				$new_order = wc_create_order();

				// Log the creation of a new order
				error_log( 'Creating new order based on Order ID: ' . $order->get_id() );

				// Set parent ID as original order ID
				$new_order->set_parent_id( $order->get_id() );

				// Set billing and shipping address from the original order
				$new_order->set_address( $order->get_address( 'billing' ), 'billing' );
				$new_order->set_address( $order->get_address( 'shipping' ), 'shipping' );

				// Add a fee line for the "PREPAID" amount as a value
				$fee = new \WC_Order_Item_Fee();
				$fee->set_name( 'PREPAID' );
				$fee->set_amount( abs( $prepaid_value ) ); // Adding the negative amount as fee
				$fee->set_tax_class( '' ); // Optional: Specify the tax class if needed
				$fee->set_total( abs( $prepaid_value ) );
				$new_order->add_item( $fee );

				$new_order->calculate_totals();
				$new_order->update_status( 'processing' );
				$new_order->add_order_note( 'Order created by custom cron job with PREPAID fee.' );
				$new_order->update_meta_data( 'parent_order_id', $order->get_id() );

				$new_order->save();

				// Mark the original order as processed to avoid processing it again
				$order->update_meta_data( 'processed_by_custom_cron_job', true );
				$order->save();

				error_log( 'New order created with ID: ' . $new_order->get_id() . ' with PREPAID value: ' . abs( $prepaid_value ) );
			}
		}

		// Log the completion of the function execution
		error_log( 'Cron job completed: create_wc_order_for_partially_paid_order' );
	}
}

/**
 *  Prepare if class 'B1CCO_Cron' exist.
 *  Kicking this off by calling 'get_instance()' method
 */
B1CCO_Cron::get_instance();
