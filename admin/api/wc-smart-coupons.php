<?php
/**
 * Custom Offer Endpoint
 *
 * @package Breeze 1-Click Checkout
 */

namespace B1CCO\Admin\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class B1CCO_WC_Smart_Coupons.
 */
class B1CCO_WC_Smart_Coupons {

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

		add_action( 'rest_api_init', array( $this, 'prefix_register_custom_offer_routes' ) );
	}

	/**
	 * This function is where we register our routes for offer endpoint.
	 */
	public function prefix_register_custom_offer_routes() {
		register_rest_route(
			'wc-custom/v3',
			'coupons/(?P<order_id>\d+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'custom_update_order' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function custom_update_order( $request ) {
		global $woocommerce;

		// Get orderId from params, use for getting order data
		$order_id = $request->get_param( 'order_id' );
		$data     = $request->get_json_params();

		// Get order by id
		$order = wc_get_order( $order_id );

		// Calculate initial order line item totals
		$order_line_item_totals = 0;
		$line_items             = $order->get_items();
		foreach ( $line_items as $item_id => $item ) {
			$item_price             = $item->get_total();
			$order_line_item_totals = $order_line_item_totals + $item_price;
		}
		// Remove Old Coupons
		$applied_coupons = $order->get_coupon_codes();
		foreach ( $applied_coupons as $coupon ) {
			$order->remove_coupon( $coupon );
		}

		// Initialize variables for total discount
		$order_total_discount = 0;

		if ( isset( $data ) && isset( $data['coupon_lines'] ) && is_array( $data['coupon_lines'] ) ) {
			foreach ( $data['coupon_lines'] as $coupon_line ) {
				$coupon_code = $coupon_line['code'];

				if ( isset( $coupon_code ) ) {
					// Get coupon id and max discount
					$coupon_id    = wc_get_coupon_id_by_code( $coupon_code );
					$max_discount = (float) get_post_meta( $coupon_id, '_wt_max_discount', true );

					// Apply coupon with max discount if _wt_max_discount is set
					if ( isset( $max_discount ) && $max_discount > 0 ) {
						$order->apply_coupon( $coupon_code, $max_discount );

						$applied_discount = $order->get_discount_total();
						if ( $applied_discount > $max_discount ) {
							foreach ( $line_items as $item_id => $item ) {
								$item_price             = $item->get_subtotal();
								$proportionate_discount = ( $item_price / $order_line_item_totals ) * $max_discount;
								$item_discount          = min( $proportionate_discount, $max_discount );
								$new_item_total         = $item_price - $item_discount;
								$item->set_total( $new_item_total );
								$order_total_discount += $item_discount;
							}
						}
					} else {
						$order->apply_coupon( $coupon_code );
					}
				}
			}
		}
		$line_items        = $this->prepare_line_item( $order );
		$coupon_lines_data = $this->prepare_coupon_lines( $order );
		$order->set_discount_total( $order_total_discount );
		$shippingLines = $this->prepare_shipping_lines( $order );
		$order->calculate_totals();
		$order->save();
		$order_data                   = $order->get_data();
		$order_data['line_items']     = $line_items;
		$order_data['coupon_lines']   = $coupon_lines_data;
		$order_data['shipping_lines'] = $shippingLines;
		return $order_data;
	}

	/**
	 * Coupon Line preparation after offer applied
	 */
	public function prepare_coupon_lines( $order ) {
		$coupon_lines      = $order->get_coupon_codes();
		$coupon_lines_data = array();
		$applied_discount  = $order->get_discount_total();

		foreach ( $coupon_lines as $coupon_code ) {
			$coupon         = new \WC_Coupon( $coupon_code );
			$coupon_payload = $coupon->get_data();

			$max_discount = null;
			foreach ( $coupon_payload['meta_data'] as $meta ) {
				$meta_array = json_decode( wp_json_encode( $meta ), true );
				if ( isset( $meta_array['key'] ) && $meta_array['key'] === '_wt_max_discount' ) {
					$max_discount = $meta_array['value'];
					break;
				}
			}

			$max_discount        = ( $applied_discount < $max_discount ) ? $applied_discount : $max_discount;
			$meta_value          = array(
				'id'                   =>
					$coupon_payload['id'],
				'code'                 => $coupon_payload['code'],
				'amount'               => ( $max_discount !== null && $max_discount !== 0 && $max_discount !== '0' ) ? $max_discount :
					$applied_discount,
				'date_created'         => $coupon_payload['date_created'],
				'discount_type'        => $coupon_payload['discount_type'],
				'description'          => $coupon_payload['description'],
				'product_ids'          => $coupon_payload['product_ids'],
				'excluded_product_ids' => $coupon_payload['excluded_product_ids'],
				'minimum_amount'       => $coupon_payload['minimum_amount'],
				'maximum_amount'       => $coupon_payload['maximum_amount'],
			);
			$coupon_lines_data[] = array(
				'code'      => $coupon_payload['code'],
				'discount'  => ( $max_discount !== null && $max_discount !== 0 && $max_discount !== '0' ) ? $max_discount :
					$applied_discount,
				'meta_data' => array(
					array(
						'value' => $meta_value,
					),
				),
			);
		}

		return $coupon_lines_data;
	}

	/**
	 * Line items preparation post offer applied
	 */
	public function prepare_line_item( $order ) {
		$line_items = $order->get_items();
		foreach ( $line_items as $item_id => $item ) {
			$line_item_data = $item->get_data();

			$product = $item->get_product();
			$image   = $product->get_image();
			$weight  = $product->get_weight();

			preg_match( '/src="(.*?)"/', $image, $matches );
			$imageUrl = isset( $matches[1] ) ? $matches[1] : '';

			$image_obj = array(
				'src' => $imageUrl,
			);

			$line_items_data[] = array(
				'id'           => $line_item_data['id'],
				'name'         => $line_item_data['name'],
				'product_id'   => $line_item_data['product_id'],
				'variation_id' => $line_item_data['variation_id'],
				'quantity'     => $line_item_data['quantity'],
				'tax_class'    => $line_item_data['tax_class'],
				'subtotal'     => $line_item_data['subtotal'],
				'subtotal_tax' => $line_item_data['subtotal_tax'],
				'total'        => $line_item_data['total'],
				'total_tax'    => $line_item_data['total_tax'],
				'taxes'        => $line_item_data['taxes'],
				'meta_data'    => $line_item_data['meta_data'],
				'sku'          => $line_item_data['sku'],
				'price'        => $line_item_data['price'],
				'parent_name'  => $line_item_data['parent_name'],
				'image'        => $image_obj,
				'weight'       => $weight,
			);
		}
		return $line_items_data;
	}

	/**
	 * Shipping Lines preparation post offer applied
	 */
	public function prepare_shipping_lines( $order ) {
		$shipping_lines = array();

		foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
			$item_data        = $item->get_data();
			$shipping_lines[] = array(
				'id'           => $item_data['id'],
				'method_title' => $item_data['method_title'],
				'method_id'    => $item_data['method_id'],
				'instance_id'  => $item_data['instance_id'],
				'total'        => $item_data['total'],
				'total_tax'    => $item_data['total_tax'],
				'taxes'        => array(),
				'meta_data'    => array(),
			);
		}

		return $shipping_lines;
	}
}

/**
 *  Prepare if class 'B1CCO_WC_Smart_Coupons' exist.
 *  Kicking this off by calling 'get_instance()' method
 */
B1CCO_WC_Smart_Coupons::get_instance();
