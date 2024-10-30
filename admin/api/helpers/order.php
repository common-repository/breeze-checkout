<?php

use Automattic\WooCommerce\StoreApi\Utilities\OrderController;

class B1CCO_BreezeWcOrderHelper
{
  static function create_order_from_cart()
  {
    $oc = new OrderController();
    $order = $oc->create_order_from_cart();
    $order->set_created_via('breeze');
    $order->save();
    return $order;
  }
  public function prepare_order_data($order)
  {
    $order_data = $order->get_data();
    $order_data = $this->prepare_line_items_data($order_data, $order);
    $order_data = $this->prepare_tax_lines_data($order_data, $order);
    $order_data = $this->prepare_coupon_lines_data($order_data, $order);
    $order_data = $this->prepare_shipping_lines_data($order_data, $order);
    $order_data = $this->prepare_fee_lines_data($order_data, $order);

    $can_show_external_taxes = get_option('b1cco_btn_enable_show_external_taxes', false);
    if ($can_show_external_taxes) {
      $order_data["total"] = strval($order_data["total"] - $order_data["total_tax"]);
    }
    return $order_data;
  }

  public function prepare_line_items_data($order_data, $order)
  {
    $line_items = $order->get_items();
    foreach ($line_items as $item_id => $item) {
      $line_item_data = $item->get_data();
      $product = $item->get_product();
      $image = $product->get_image();
      $weight = ($product->get_weight() === "") ? '0' : $product->get_weight();

      preg_match('/src="(.*?)"/', $image, $matches);
      $imageUrl = isset($matches[1]) ? $matches[1] : '';

      $image_obj = array(
        'src' => $imageUrl,
      );

      $can_show_external_taxes = get_option('b1cco_btn_enable_show_external_taxes', false);
      if ($can_show_external_taxes) {
        $total_price = wc_format_decimal(($line_item_data['total']), 2);
        $subtotal_price = wc_format_decimal(($line_item_data['subtotal']), 2);
      } else {
        $total_price = wc_format_decimal(($line_item_data['total'] + $line_item_data['total_tax']), 2);
        $subtotal_price = wc_format_decimal(($line_item_data['subtotal'] + $line_item_data['subtotal_tax']), 2);
      }

      $line_items_data[] = array(
        'id' => $line_item_data['id'],
        'name' => $line_item_data['name'],
        'product_id' => $line_item_data['product_id'],
        'variation_id' => $line_item_data['variation_id'],
        'quantity' => $line_item_data['quantity'],
        'tax_class' => $line_item_data['tax_class'],
        'subtotal' => strval($subtotal_price),
        'subtotal_tax' => $line_item_data['subtotal_tax'],
        'total' => strval($total_price),
        'total_tax' => $line_item_data['total_tax'],
        'taxes' => $line_item_data['taxes'],
        'meta_data' => $line_item_data['meta_data'],
        'image' => $image_obj,
        'weight' => $weight,
      );
    }
    $order_data['line_items'] = $line_items_data;
    return $order_data;
  }

  public function prepare_tax_lines_data($order_data, $order)
  {
    $tax_totals_data = $order->get_tax_totals();
    $converted_tax_lines = array();
    foreach ($tax_totals_data as $key => $tax_total) {
      $tax_total = (array) $tax_total;
      $id = $tax_total['id'];
      $rate_id = $tax_total['rate_id'];
      $rate_code = \WC_Tax::get_rate_code($rate_id);
      $label = $tax_total['label'];
      $compound = $tax_total['is_compound'];
      $tax_total_amount = $tax_total['amount'];
      $shipping_tax_total = WC()->cart->get_shipping_tax_amount($rate_id);
      $rate_percent = \WC_Tax::get_rate_percent_value($rate_id);

      $tax_line_details = array(
        'id' => $id,
        'rate_code' => $rate_code,
        'rate_id' => $rate_id,
        'label' => $label,
        'compound' => $compound,
        'tax_total' => strval($tax_total_amount),
        'shipping_tax_total' => strval($shipping_tax_total),
        'rate_percent' => $rate_percent
      );
      $converted_tax_lines[] = $tax_line_details;
    }
    $order_data['tax_lines'] = $converted_tax_lines;
    return $order_data;
  }

  public function prepare_shipping_lines_data($order_data, $order)
  {
    $order->remove_order_items("shipping");
    $order->calculate_totals();
    $order->save();

    $order_total = $order->get_total();
    $order_data['shipping_lines'] = [];
    $order_data['total'] = strval($order_total);
    return $order_data;
  }

  public function prepare_fee_lines_data($order_data, $order)
  {
    $order->remove_order_items("fee");
    $order->calculate_totals();
    $order->save();

    $order_total = $order->get_total();
    $order_data['fee_lines'] = [];
    $order_data['total'] = strval($order_total);
    return $order_data;
  }

  public function prepare_coupon_lines_data($order_data, $order)
  {
    $discount_totals = WC()->cart->get_coupon_discount_totals();
    $discount_tax_totals = WC()->cart->get_coupon_discount_tax_totals();

    $coupon_lines = [];
    foreach ($discount_totals as $code => $discount) {
      $total_discount = $discount + $discount_tax_totals[$code];
      $formatted_discount = strval($total_discount * 100);
      $coupon_lines[] = ["code" => $code, "discount" => $formatted_discount];
    }
    $order_data['coupon_lines'] = $coupon_lines;
    // $order_data['coupon1'] = $discount_totals;
    // $order_data['coupon2'] = $discount_tax_totals;
    return $order_data;
  }
}

