<?php
/**
 * Add Breeze Buttons
 *
 * @package Breeze 1-Click Checkout
 */

namespace B1CCO\Admin\Ajax;

require_once __DIR__ . '/../api/helpers/order.php';
use B1CCO_BreezeWcOrderHelper;
use WC_Tax;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * Class B1CCO_Ajax_Action.
 */
class B1CCO_Ajax_Action
{

	/**
	 * Member Variable
	 *
	 * @var instance
	 */
	private static $instance;

	public $options;

	/**
	 *  Initiator
	 */
	public static function get_instance()
	{
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->options = get_option('b1cco_plugin_options');

		// Add Custom property weight to support shipping based on weight
		add_filter('woocommerce_rest_prepare_shop_order_object', array($this, 'add_weight_to_order_response'), 10, 3);
		add_filter('woocommerce_rest_prepare_shop_order_object', array($this, 'add_weight_to_line_items'), 10, 3);


		// Set Local Atoms.
		add_action('wp_ajax_set_local_atoms', array($this, 'set_local_atoms'));
		add_action('wp_ajax_nopriv_set_local_atoms', array($this, 'set_local_atoms'));

		// Get Cookie Data.
		add_action('wp_ajax_get_utm_params', array($this, 'get_utm_params'));
		add_action('wp_ajax_nopriv_get_utm_params', array($this, 'get_utm_params'));

		// Empty cart items.
		add_action('wp_ajax_empty_cart', array($this, 'empty_cart'));
		add_action('wp_ajax_nopriv_empty_cart', array($this, 'empty_cart'));

		// Add current product to cart.
		add_action('wp_ajax_add_current_product', array($this, 'add_current_product'));
		add_action('wp_ajax_nopriv_add_current_product', array($this, 'add_current_product'));

		// Add order metadata via AJAX.
		add_action('wp_ajax_add_order_metadata', array($this, 'add_order_metadata'));
		add_action('wp_ajax_nopriv_add_order_metadata', array($this, 'add_order_metadata'));

		// Create Order.
		add_action('wp_ajax_create_order', array($this, 'create_order'));
		add_action('wp_ajax_nopriv_create_order', array($this, 'create_order'));

		// cartflow cart abandonment support.
		add_action('woocommerce_update_order', array($this, 'cartflow_cart_abandonment_support_via_actionhook'), 10, 1);
		// validae order if any order status is reverting from processing to pending.
		add_action('woocommerce_order_status_changed', array($this, 'validate_order_status_and_update'), 10, 4);
	}
	public function add_weight_to_order_response($response, $object, $request)
	{
		// Ensure the object is an instance of the expected class
		if (!is_a($object, 'WC_Order')) {
			return $response;
		}

		$order = wc_get_order($object->get_id());

		if (!$order) {
			return $response; // Bail if the order is invalid
		}

		// Initialize total weight
		$total_weight = 0;

		// Get the option to enable weight conversion to grams
		$weight_in_gm = get_option('b1cco_btn_enable_product_weight_in_gm', false);

		foreach ($order->get_items() as $item_id => $item) {
			$product = $item->get_product();

			// Validate if product exists and has weight
			if ($product && $product->has_weight()) {
				$quantity = (int) $item->get_quantity();
				$weight = (float) $product->get_weight();

				// Add product weight to total, multiplying by quantity
				$total_weight += $weight * $quantity;
			}
		}

		// Determine multiplier based on option
		$multiplier = $weight_in_gm === true ? 1000 : 1;

		// Add total weight to response, possibly converting to grams
		$response->data['total_weight'] = sanitize_text_field(strval($multiplier * $total_weight));

		return $response;
	}

	public function add_weight_to_line_items($response, $object, $request)
	{
		// Ensure the object is an instance of the expected class
		if (!is_a($object, 'WC_Order')) {
			return $response;
		}

		$order = wc_get_order($object->get_id());

		if (!$order) {
			return $response; // Bail if the order is invalid
		}

		// Get the option to enable weight conversion to grams
		$weight_in_gm = get_option('b1cco_btn_enable_product_weight_in_gm', false);

		// Iterate through line items and add weight where applicable
		if (isset($response->data['line_items']) && is_array($response->data['line_items'])) {
			foreach ($response->data['line_items'] as &$line_item) {
				// Validate product ID existence
				if (isset($line_item['product_id'])) {
					$product_id = intval($line_item['product_id']);
					$product = wc_get_product($product_id);

					// If product exists and has weight, add it to the line item
					if ($product && $product->has_weight()) {
						$multiplier = $weight_in_gm === true ? 1000 : 1;
						$line_item['weight'] = sanitize_text_field(strval($multiplier * $product->get_weight()));
					} else {
						$line_item['weight'] = '0';
					}
				}
			}
		}
		return $response;
	}

	public function set_local_atoms()
	{
		if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'set_local_atoms')) {
			wp_send_json_error('bad_nonce');
			wp_die();
		}
		if (!isset($_POST['localAtomsValue'])) {
			wp_send_json_error('missing_localAtomsValue_fields');
			wp_die();
		}

		$localAtomsValue = sanitize_text_field(wp_unslash($_POST['localAtomsValue']));
		$localAtomUrl = get_option('b1cco_get_local_atom_url');
		if ($localAtomsValue === 'yes' || $localAtomsValue === 'YES') {
			$script_url = $localAtomUrl;
		} else {
			$script_url = B1CCO_FILE_URL . 'build/breeze.js';
		}

		$update_success = update_option('b1cco_script_url', $script_url);

		if ($update_success) {
			$response = array(
				'status' => 'success',
				'result' => wp_json_encode($script_url),
			);
		} else {
			$response = array(
				'status' => 'error',
				'result' => 'Failed to update script url!',
			);
		}
		wp_send_json($response);
		wp_die();
	}

	public function get_utm_params()
	{
		if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'get_utm_params')) {
			wp_send_json_error('bad_nonce');
			wp_die();
		}

		if (!isset($_POST['source_type'], $_POST['utm_source'], $_POST['device_type'], $_POST['session_pages'], $_POST['utm_medium'], $_POST['utm_campaign'], $_POST['utm_origin'])) {
			wp_send_json_error('missing_one_of_utm_params_fields');
			wp_die();
		}

		$source_type = sanitize_text_field(wp_unslash($_POST['source_type']));
		$utm_source = sanitize_text_field(wp_unslash($_POST['utm_source']));
		$device_type = sanitize_text_field(wp_unslash($_POST['device_type']));
		$session_pages = sanitize_text_field(wp_unslash($_POST['session_pages']));
		$utm_medium = sanitize_text_field(wp_unslash($_POST['utm_medium']));
		$utm_campaign = sanitize_text_field(wp_unslash($_POST['utm_campaign']));
		$utm_origin = sanitize_text_field(wp_unslash($_POST['utm_origin']));

		$data = array(
			'source_type' => $source_type,
			'utm_source' => $utm_source,
			'device_type' => $device_type,
			'session_pages' => $session_pages,
			'utm_medium' => $utm_medium,
			'utm_campaign' => $utm_campaign,
			'utm_origin' => $utm_origin,
		);

		// Update global variable
		$update_success = update_option('b1cco_get_custom_cookie_data', $data);

		if ($update_success) {
			$response = array(
				'status' => 'success',
				'result' => wp_json_encode($data),
			);
		} else {
			$response = array(
				'status' => 'failure',
				'result' => 'Failed to update cookie data!',
			);
		}
		wp_send_json($response);
		wp_die();
	}

	public function empty_cart()
	{
		if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'empty_cart')) {
			wp_send_json_error('bad_nonce');
			wp_die();
		}

		WC()->cart->empty_cart();

		if (WC()->cart->is_empty()) {
			$response = array(
				'status' => 'success',
				'result' => 'Cart emptied successfully!',
			);
		} else {
			$response = array(
				'status' => 'failure',
				'result' => 'Failed to empty cart!',
			);
		}
		wp_send_json($response);
		wp_die();
	}

	public function add_current_product()
	{
		if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'add_current_product')) {
			wp_send_json_error('bad_nonce');
			wp_die();
		}

		WC()->cart->empty_cart();

		$product_id = isset($_POST['productId']) ? sanitize_text_field(wp_unslash($_POST['productId'])) : '';
		$quantity = isset($_POST['quantity']) ? sanitize_text_field(wp_unslash($_POST['quantity'])) : 1; // Default quantity to 1 if not provided
		$variation_id = isset($_POST['variationId']) ? sanitize_text_field(wp_unslash($_POST['variationId'])) : 0; // Default variationId to 0 if not provided

		$variantData = isset($_POST['variantsData']) ? sanitize_text_field(wp_unslash($_POST['variantsData'])) : null;
		$variations = array();
		if ($variantData !== null) {
			foreach ($variantData as $key => $value) {
				$sanitizedKey = sanitize_text_field($key);
				$sanitizedValue = sanitize_text_field($value);
				if ('attribute_' !== substr($sanitizedKey, 0, 10)) {
					continue;
				}
				$variations[$sanitizedKey] = $sanitizedValue;
			}
		}

		$success_result = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variations);

		if ($success_result !== false) {
			$response = array(
				'status' => 'success',
				'result' => $success_result,
			);
		} else {
			$response = array(
				'status' => 'failure',
				'result' => $success_result,
			);
		}
		wp_send_json($response);
		wp_die();
	}
	public function add_order_metadata()
	{
		if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'add_order_metadata')) {
			wp_send_json_error('bad_nonce');
			wp_die();
		}

		$order_id = isset($_POST['order_id']) ? sanitize_text_field(wp_unslash($_POST['order_id'])) : '';
		$metadata_key = isset($_POST['metadata_key']) ? sanitize_text_field(wp_unslash($_POST['metadata_key'])) : '';
		$metadata_value = isset($_POST['metadata_value']) ? sanitize_text_field(wp_unslash($_POST['metadata_value'])) : '';

		$update_result = update_post_meta($order_id, $metadata_key, $metadata_value);

		if ($update_result !== false) {
			$response = array(
				'status' => 'success',
				'result' => wp_json_encode($update_result),
			);
		} else {
			$response = array(
				'status' => 'failure',
				'result' => 'Failed to add metadata.',
			);
		}
		wp_send_json($response);
		wp_die();
	}

	public function create_order()
	{
		if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'create_order')) {
			wp_send_json_error('bad_nonce');
			wp_die();
		}

		$order = B1CCO_BreezeWcOrderHelper::create_order_from_cart();
		$response = $this->create_abandonment_cart_data($order);
		$order = $this->update_order_meta_data_based_on_enable_utm($order);

		$bzOrder = new B1CCO_BreezeWcOrderHelper();
		$order_data = $bzOrder->prepare_order_data($order);

		wp_send_json_success(array('order' => $order_data));
		wp_die();
	}

	// ----------------------------------------------------
	// ALL UTILITY FUNCTIONS IMPLEMENTATION START FROM HERE
	// ----------------------------------------------------


	// Define the function to handle updating meta data based on the enable_utm option
	public function update_order_meta_data_based_on_enable_utm($order)
	{
		$enable_utm = get_option('b1cco_btn_enable_utm_params', false);

		if ($enable_utm) {
			// Retrieve custom cookie data and ensure it's an array
			$my_custom_data = get_option('b1cco_get_custom_cookie_data', array());
			if (!is_array($my_custom_data) || empty($my_custom_data)) {
				return $order; // Return early if custom data is missing or invalid
			}

			// Define the custom data keys to use
			$custom_data_keys = array(
				"_wc_order_attribution_source_type" => "source_type",
				"_wc_order_attribution_utm_source" => "utm_source",
				"_wc_order_attribution_device_type" => "device_type",
				"_wc_order_attribution_session_pages" => "session_pages",
				"_wc_order_attribution_utm_medium" => "utm_medium",
				"_wc_order_attribution_utm_campaign" => "utm_campaign",
				"_wc_order_attribution_utm_origin" => "utm_origin"
			);

			// Loop through custom data keys and validate each key
			foreach ($custom_data_keys as $meta_key => $data_key) {
				// Check if the data key exists in the custom data and is not empty
				if (isset($my_custom_data[$data_key]) && !empty($my_custom_data[$data_key])) {
					$order->update_meta_data($meta_key, sanitize_text_field($my_custom_data[$data_key]));
				}
			}
			$order->save();
		}

		return $order;
	}

	public function create_abandonment_cart_data($order)
	{
		// Check if the "enable cartflow cart abandonment" parameter is selected
		$enable_cartflow_abandonment = get_option('b1cco_btn_enable_cartflow_abandoned_flow', false);

		if ($enable_cartflow_abandonment) {
			global $wpdb;
			$order_data = $order->get_data();

			$provider_other_data = array(
				'wcf_billing_company' => '',
				'wcf_billing_address_1' => $order_data['billing']['address_1'] ?? '',
				'wcf_billing_address_2' => $order_data['billing']['address_2'] ?? '',
				'wcf_billing_state' => $order_data['billing']['state'] ?? '',
				'wcf_billing_postcode' => $order_data['billing']['postcode'] ?? '',
				'wcf_shipping_first_name' => $order_data['shipping']['first_name'] ?? '',
				'wcf_shipping_last_name' => $order_data['shipping']['last_name'] ?? '',
				'wcf_shipping_company' => '',
				'wcf_shipping_country' => $order_data['shipping']['country'] ?? '',
				'wcf_shipping_address_1' => $order_data['shipping']['address_1'] ?? '',
				'wcf_shipping_address_2' => $order_data['shipping']['address_2'] ?? '',
				'wcf_shipping_city' => $order_data['shipping']['city'] ?? '',
				'wcf_shipping_state' => $order_data['shipping']['state'] ?? '',
				'wcf_shipping_postcode' => $order_data['shipping']['postcode'] ?? '',
				'wcf_order_comments' => '',
				'wcf_first_name' => $order_data['shipping']['first_name'] ?? '',
				'wcf_last_name' => '',
				'wcf_phone_number' => $order_data['shipping']['phone'] ?? '',
			);
			$current_time = current_time('Y-m-d H:i:s');
			$cart_contents = WC()->cart->get_cart();

			// Start PHP session if not already started
			if (!session_id()) {
				session_start();
			}

			// Get or generate session ID
			if (isset($_SESSION['wcf_session_id'])) {
				$sessionId = sanitize_text_field(wp_unslash($_SESSION['wcf_session_id']));
			} else {
				$sessionId = md5(uniqid(wp_rand(), true));
				$_SESSION['wcf_session_id'] = $sessionId;
			}

			WC()->session->set('wcf_session_id', $sessionId);

			$checkout_details = array(
				'email' => $order_data['billing']['email'] ?? '',
				'cart_contents' => serialize($cart_contents),
				'cart_total' => sanitize_text_field($order_data['total']),
				'time' => $current_time,
				'order_status' => 'normal',
				'other_fields' => serialize($provider_other_data),
				'checkout_id' => wc_get_page_id('cart'),
				'session_id' => $sessionId,
			);
			$order->update_meta_data('b1cco_session_token', $sessionId);
			$order->save();

			$cart_abandonment_table = $wpdb->prefix . 'cartflows_ca_cart_abandonment';

			if (empty($checkout_details) == false) {
				$result = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM $cart_abandonment_table WHERE session_id = %s AND order_status IN (%s, %s)",
						$sessionId,
						'normal',
						'abandoned'
					)
				);

				if (isset($result)) {
					$wpdb->update(
						$cart_abandonment_table,
						$checkout_details,
						array('session_id' => $sessionId)
					);
					if ($wpdb->last_error) {
						$response['status'] = false;
						$response['message'] = $wpdb->last_error;
					} else {
						$response['status'] = true;
						$response['message'] = 'Data successfully updated for wooCommerce cart abandonment recovery';
					}
				} else {
					$wpdb->insert(
						$cart_abandonment_table,
						$checkout_details
					);

					if ($wpdb->last_error) {
						$response['status'] = false;
						$response['message'] = $wpdb->last_error;
						$statusCode = 400;
					} else {
						// WC()->session->set('wcf_session_id', $sessionId);
						$response['status'] = true;
						$response['message'] = 'Data successfully inserted for wooCommerce cart abandonment recovery';
						$statusCode = 200;
					}
				}
			}
			// TODO: log the response with sessionId
			// return $response;
		}

		// Return the updated order object
		return $order;
	}

	public function cartflow_cart_abandonment_support_via_actionhook($order_id)
	{
		// Check if the "enable cartflow cart abandonment" parameter is selected
		$enable_cartflow_abandonment = get_option('b1cco_btn_enable_cartflow_abandoned_flow', false);

		if ($enable_cartflow_abandonment) {
			global $wpdb;
			$order = wc_get_order($order_id);
			$order_data = $order->get_data();
			$new_status = $order_data['status'];

			$sessionId = '';
			foreach ($order_data['meta_data'] as $meta) {
				if ($meta->key === 'b1cco_session_token') {
					$sessionId = $meta->value;
					break; // Break loop once found
				}
			}

			if (isset($sessionId)) {
				$cart_abandonment_table = $wpdb->prefix . 'cartflows_ca_cart_abandonment';
				if ($new_status === 'processing') {
					$wpdb->delete($cart_abandonment_table, array('session_id' => sanitize_key($sessionId)));

					if ($wpdb->last_error) {
						$response['status'] = false;
						$response['message'] = $wpdb->last_error;
					} else {
						$response['status'] = true;
						$response['message'] = 'Data successfully deleted for wooCommerce cart abandonment recovery';
					}
				} else {
					$provider_other_data = array(
						'wcf_billing_company' => '',
						'wcf_billing_address_1' => $order_data['billing']['address_1'] ?? '',
						'wcf_billing_address_2' => $order_data['billing']['address_2'] ?? '',
						'wcf_billing_state' => $order_data['billing']['state'] ?? '',
						'wcf_billing_postcode' => $order_data['billing']['postcode'] ?? '',
						'wcf_shipping_first_name' => $order_data['shipping']['first_name'] ?? '',
						'wcf_shipping_last_name' => $order_data['shipping']['last_name'] ?? '',
						'wcf_shipping_company' => '',
						'wcf_shipping_country' => $order_data['shipping']['country'] ?? '',
						'wcf_shipping_address_1' => $order_data['shipping']['address_1'] ?? '',
						'wcf_shipping_address_2' => $order_data['shipping']['address_2'] ?? '',
						'wcf_shipping_city' => $order_data['shipping']['city'] ?? '',
						'wcf_shipping_state' => $order_data['shipping']['state'] ?? '',
						'wcf_shipping_postcode' => $order_data['shipping']['postcode'] ?? '',
						'wcf_order_comments' => '',
						'wcf_first_name' => $order_data['shipping']['first_name'] ?? '',
						'wcf_last_name' => '',
						'wcf_phone_number' => $order_data['shipping']['phone'] ?? '',
					);
					$current_time = current_time('Y-m-d H:i:s');

					$checkout_details = array(
						'email' => $order_data['billing']['email'] ?? '',
						'cart_total' => sanitize_text_field($order_data['total']),
						'time' => $current_time,
						'order_status' => 'normal',
						'other_fields' => serialize($provider_other_data),
						'checkout_id' => wc_get_page_id('cart'),
					);

					if (empty($checkout_details) == false) {
						$wpdb->update(
							$cart_abandonment_table,
							$checkout_details,
							array('session_id' => $sessionId)
						);
						if ($wpdb->last_error) {
							$response['status'] = false;
							$response['message'] = $wpdb->last_error;
						} else {
							$response['status'] = true;
							$response['message'] = 'Data successfully updated for wooCommerce cart abandonment recovery';
						}
					}
				}
			}
			return $response;
		}
	}

	public function validate_order_status_and_update($order_id, $old_status, $new_status, $order)
	{
		// Check if the "enable cartflow cart abandonment" parameter is selected
		$enable_validate_order_status = get_option('b1cco_btn_enable_validate_order_status');

		if ($enable_validate_order_status) {
			if ($old_status === 'processing' && ($new_status === 'checkout-draft' || $new_status === 'pending')) {
				$order->set_status('processing');
				$order->save();
			}
		}
		return $order;
	}
}

/**
 *  Prepare if class 'B1CCO_Ajax_Action' exist.
 *  Kicking this off by calling 'get_instance()' method
 */
B1CCO_Ajax_Action::get_instance();
