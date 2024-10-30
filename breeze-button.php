<?php
/**
 * Plugin Name:       Breeze Checkout
 * Description:       Give your customers an enchanting checkout experience. Breeze is created by the team that handles over 20 million payments every day.
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Version:           1.0.0
 * Author:            Juspay Technologies PVT LTD
 * Author URI:        https://breeze.in/
 * Developer:         Juspay Technologies PVT LTD
 * Developer URI:     https://breeze.in/
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package           juspay
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

define('B1CCO_FILE', __FILE__);
define('B1CCO_PLUGIN_STATUS_HOOK_URL', '<plugin_status_url>');
define('B1CCO_ORDER_REFUND_WEBHOOK_URL', 'https://api.breeze.in/order/refund/webhook/<shop_id>');
define('B1CCO_REFUND_WEBHOOK_SIGN_KEY', '48304eb47085c8996815aacaf1862f4352d3788f507dbd4d4dd51bf86ab686e33c14b1020b85027321443e7e80cbd251');

// Include necessary files.
require_once __DIR__ . '/admin/hooks/main.php';
require_once __DIR__ . '/admin/api/wc-smart-coupons.php';
require_once __DIR__ . '/admin/ajax/main.php';
require_once __DIR__ . '/admin/inc/add-breeze-buttons.php';
require_once __DIR__ . '/admin/inc/remove-native-buttons.php';
require_once __DIR__ . '/admin/cron/main.php';
require_once __DIR__ . '/admin/hooks/refund.php';

$plugin_instance = B1CCO\Admin\Hooks\B1CCO_Hooks::get_instance();

register_activation_hook(__FILE__, array($plugin_instance, 'on_activate'));
register_deactivation_hook(__FILE__, array($plugin_instance, 'on_deactivate'));

// External Plugin Support
use B1CCO\Admin\Api\B1CCO_WC_Smart_Coupons;
use B1CCO\Admin\Ajax\B1CCO_Ajax_Action;
use B1CCO\Admin\Inc\B1CCO_Add_Buttons;
use B1CCO\Admin\Inc\B1CCO_Remove_Native_Buttons;
use B1CCO\Admin\Cron\B1CCO_Cron;
use B1CCO\Admin\Hooks\B1CCO_Order_Refund;

// Check if the class exists before defining it.
if (!class_exists('B1CCO_Checkout')) {

	// Define the class
	class B1CCO_Checkout
	{
		private static $instance;
		public $b1cco_script_url;

		// Get instance of the class.
		public static function get_instance()
		{
			if (!isset(self::$instance)) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		// Constructor.
		public function __construct()
		{
			$this->define_constants();
			$this->b1cco_script_url = get_option('b1cco_script_url');

			add_action('wp_head', array($this, 'b1cco_custom_script_tag'));
			add_action('init', array($this, 'setup_classes'));
			add_action('wp_enqueue_scripts', array($this, 'register_scripts'));

			// Add Custom Order Status as 'Abandoned'.
			add_action('init', array($this, 'b1cco_register_abandoned_order_status'));
			add_filter('wc_order_statuses', array($this, 'b1cco_add_abandoned_to_order_statuses'));
		}

		// Define constants.
		public function define_constants()
		{
			define('B1CCO_FILE_VER', '0.1');
			define('B1CCO_FILE_BASE', plugin_basename(B1CCO_FILE));
			define('B1CCO_FILE_DIR', plugin_dir_path(B1CCO_FILE));
			define('B1CCO_FILE_URL', plugins_url('/', B1CCO_FILE));

			update_option('b1cco_btn_enable_utm_params', true);
			update_option('b1cco_btn_enable_cartflow_abandoned_flow', false);
			update_option('b1cco_btn_enable_validate_order_status', false);

			// Weight prop in order api response
			update_option('b1cco_btn_enable_product_weight_in_gm', false);

			// External Taxes
			update_option('b1cco_btn_enable_show_external_taxes', false);

			// Cron Jobs
			update_option('b1cco_btn_enable_custom_cron_jobs', false);
			update_option('b1cco_btn_enable_cron_job_for_partially_paid_order_creation', false);

			// Setup refund
			update_option('b1cco_btn_enable_order_refund', false);
		}

		// Register scripts and styles.
		public function register_scripts()
		{
			// Create nonces
			$nonces = $this->create_nonces();
			$localization_data = array_merge(array('ajaxurl' => admin_url('admin-ajax.php')), $nonces);

			wp_register_script('breeze-script', 'https://sdk.breeze.in/electron/151.0.0/index.js', array('jquery', 'wp-util'), B1CCO_FILE_VER, true);
			wp_register_script('breeze-custom-script', B1CCO_FILE_URL . 'build/custom.js', array('jquery', 'wp-util'), B1CCO_FILE_VER, true);
			wp_register_style('breeze-style', B1CCO_FILE_URL . 'build/style-index.css', null, B1CCO_FILE_VER, 'all');

			// Localize script with AJAX URL.
			wp_localize_script('breeze-script', 'breezeAjax', $localization_data);

			// Enqueue scripts and styles.
			wp_enqueue_script('jquery');
			wp_enqueue_script('breeze-script');
			wp_enqueue_script('breeze-custom-script');
			wp_enqueue_style('breeze-style');
		}

		// Setup other classes.
		public function setup_classes()
		{
			// Uncomment and use if needed.
			B1CCO_Ajax_Action::get_instance();
			B1CCO_WC_Smart_Coupons::get_instance();
			B1CCO_Add_Buttons::get_instance();
			B1CCO_Remove_Native_Buttons::get_instance();
			B1CCO_Cron::get_instance();
			B1CCO_Order_Refund::get_instance();
		}

		public function b1cco_custom_script_tag()
		{
			echo '<script 
					id="breeze-script-tag"
					data-environment="release" 
					data-platform="woocommerce" 
					data-enable-external-trackers="true" 
					data-enable-snap-tracker="false" 
					data-enable-ga="false" 
					data-enable-fbp="false" 
					data-emit-tracker-events="true" 
					data-ga4-measurement-id="G-CHANGE" 
					data-merchantid="CHANGE" 
					data-ga-version="new"
					ghost-mode="true">
				</script>';
		}

		public function create_nonces()
		{
			return array(
				'set_local_atoms_nonce' => wp_create_nonce('set_local_atoms'),
				'get_utm_params_nonce' => wp_create_nonce('get_utm_params'),
				'add_current_product_nonce' => wp_create_nonce('add_current_product'),
				'add_order_metadata_nonce' => wp_create_nonce('add_order_metadata'),
				'create_order_nonce' => wp_create_nonce('create_order'),
				'empty_cart_nonce' => wp_create_nonce('empty_cart')
			);
		}

		// Register the abandoned order status.
		public function b1cco_register_abandoned_order_status()
		{
			register_post_status('wc-abandoned', array(
				'label' => _x('Abandoned', 'Order status', 'breeze-checkout'),
				'public' => true,
				'exclude_from_search' => false,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				// Translators: %s is the number of abandoned orders.
				'label_count' => _n_noop('Abandoned (%s)', 'Abandoned (%s)', 'breeze-checkout'),
			));
		}

		// Add abandoned order status to the WooCommerce statuses.
		public function b1cco_add_abandoned_to_order_statuses($order_statuses)
		{
			$new_statuses = array();

			// Inserting the new status after "Pending Payment".
			foreach ($order_statuses as $key => $status) {
				$new_statuses[$key] = $status;

				if ('wc-pending' === $key) {
					$new_statuses['wc-abandoned'] = _x('Abandoned', 'Order status', 'breeze-checkout');
				}
			}

			return $new_statuses;
		}
	}

	// Initialize the class.
	B1CCO_Checkout::get_instance();
}