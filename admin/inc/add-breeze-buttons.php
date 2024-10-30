<?php
/**
 * Add Breeze Buttons
 *
 * @package Breeze 1-Click Checkout
 */

namespace B1CCO\Admin\Inc;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class B1CCO_Add_Buttons.
 */
class B1CCO_Add_Buttons {

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
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'b1cco_button_on_product_page' ), 0 );
		add_action( 'woocommerce_after_cart_totals', array( $this, 'b1cco_button_on_cart_page' ), 0 );
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'b1cco_button_on_cart_page' ), 0 );
		add_action( 'woocommerce_widget_shopping_cart_after_buttons', array( $this, 'b1cco_button_on_cart_drawer_page' ), 0 );

		// Custom button injection
		// add_action('wp_head', array($this, 'breeze_button_on_cart_drawer'), 0);
	}

	public function breeze_button_on_cart_drawer() {
		$nonce = wp_create_nonce( 'wc_store_api' );
		wc_enqueue_js(
			"
			var observerMutation = false;

			function insertBreezeButton() {
				// Create a new breeze-button element
				var breezeButton = document.createElement('breeze-button');
				breezeButton.id = 'breeze-button';
				breezeButton.setAttribute('wpnonce', '" . $nonce . "');
				breezeButton.setAttribute('errortext', '');
				breezeButton.setAttribute('buynowtext', 'Buy it now');
				breezeButton.setAttribute('checkouttext', 'PROCEED TO CHECKOUT');
				breezeButton.setAttribute('buttoncolor', '#f55f1e');
				breezeButton.setAttribute('buttonradius', '0px');
				breezeButton.setAttribute('buttonhovercolor', '#e85a1c');
				breezeButton.setAttribute('showlogo', 'false');
				breezeButton.setAttribute('texthovercolor', '#ffffff');
				breezeButton.setAttribute('buttonpadding', '15px');
				breezeButton.setAttribute('fontfamily', 'Poppins');
				breezeButton.setAttribute('buttonminwidth', '0px');
				breezeButton.setAttribute('buttonfontweight', '400');
				breezeButton.setAttribute('buttonfontsize', '16px');
				breezeButton.setAttribute('letterspacing', '-0.3px');

				// Create a container for the breeze-button
				var btnContainer = document.createElement('div');
				btnContainer.id = 'breeze-button-container';
				btnContainer.style.display = 'inline-block';
				btnContainer.style.marginTop = '0px';
				btnContainer.style.width = '100%';
				btnContainer.appendChild(breezeButton);

				// Find all elements with the class 'buttons' and replace any existing breeze-button
				var elements = document.querySelectorAll('p.buttons');
				elements.forEach(function(element) {
					// Remove existing breeze-button if found
					var existingButton = element.querySelector('breeze-button');
					if (existingButton) {
						existingButton.remove();
					}
					// Append the new breeze-button
					element.appendChild(btnContainer.cloneNode(true));
				});
			}

			function observeMutation() {
				var targetElement = document.querySelector('.mcart-border');
				console.log('Target element:', targetElement);

				if (targetElement) {
					var observer = new MutationObserver(function(mutations) {
						mutations.forEach(function(mutation) {
						// Check for the presence of breeze-button
						var breezeButtonExists = targetElement.querySelector('breeze-button');
						console.log('Breeze button found:', breezeButtonExists);
						console.log(mutation.type,'type');
						console.log(mutation.addedNodes.length,'dscds');
						// Always replace the breeze-button when changes are detected
							if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
								insertBreezeButton();
							}
						});
					});

					var config = { childList: true, subtree: true }; // Observe all descendant nodes
					observer.observe(targetElement, config);
				} else {
					console.log('Target element not found');
				}
			}

			// Initialize the functions
			observeMutation();
			insertBreezeButton();
		"
		);
	}

	// create button on product page
	// TODO: button property should be configurable from User Interface
	public function b1cco_button_on_product_page() {
		global $product;
		$id    = $product->get_id();
		$arr   = array(
			'productId' => (string) $id,
		);
		$nonce = wp_create_nonce( 'wc_store_api' );
		echo '
			<div style="margin-top: 10px; display: inline-block; width: 100%;" id="breeze-button-container">
				<breeze-button
					id="breeze-button"
					wpnonce=' . esc_attr( $nonce ) . '
					productdata=' . esc_attr( base64_encode( wp_json_encode( $arr ) ) ) . '
					errortext=""
					buynowtext="Checkout"
					checkouttext="Checkout"
					buttoncolor="#000000"
					buttonhovercolor="#d8246c"
					buttonborder=""
					buttonhoverborder=""
					buttonradius="3px"
					buttonminheight=""
					buttonminwidth=""
					buttonfontweight="700"
					buttonfontsize="13px"
					buttonpadding="5px auto"
					showlogo="false"
					logocolor="white"
					logohovercolor="white"
					fontfamily="Montserrat">
				</breeze-button>
    	</div>
    ';
	}

	// create button on checkout page
	// TODO: button property should be configurable from User Interface
	public function b1cco_button_on_cart_drawer_page() {
		$nonce = wp_create_nonce( 'wc_store_api' );
		echo '
			<div style=" width:100%;"  id="breeze-button-container">
				<breeze-button
					id="breeze-button"
					wpnonce=' . esc_attr( $nonce ) . '
					errortext=""
					buynowtext="FAST CHECKOUT"
					checkouttext="FAST CHECKOUT"
					buttoncolor="#000000"
					buttonhovercolor="#d8246c"
					buttonborder=""
					buttonhoverborder=""
					buttonradius="3px"
					buttonminheight=""
					buttonminwidth=""
					buttonfontweight="700"
					buttonfontsize="13px"
					buttonpadding="5px auto"
					showlogo="false"
					logocolor="white"
					logohovercolor="white"
					fontfamily="Montserrat">
				</breeze-button>
			</div>
		';
	}

	// create button on cart page
	// TODO: button property should be configurable from User Interface
	public function b1cco_button_on_cart_page() {
		$nonce = wp_create_nonce( 'wc_store_api' );
		echo '
			<div style="margin-top: 10px; display: inline-block; width: 100%; margin-bottom: 10px;" id="breeze-button-container" class="desktop_button">
				<breeze-button
					id="breeze-button"
					wpnonce=' . esc_attr( $nonce ) . '
					errortext=""
					buynowtext="Checkout"
					checkouttext="Checkout"
					buttoncolor="#000000"
					buttonhovercolor="#d8246c"
					buttonborder=""
					buttonhoverborder=""
					buttonradius="3px"
					buttonminheight=""
					buttonminwidth=""
					buttonfontweight="700"
					buttonfontsize="13px"
					buttonpadding="5px auto"
					showlogo="false"
					logocolor="white"
					logohovercolor="white"
					fontfamily="Montserrat">
				</breeze-button>
			</div>
		';
	}
}

/**
 *  Prepare if class 'B1CCO_Add_Buttons' exist.
 *  Kicking this off by calling 'get_instance()' method
 */
B1CCO_Add_Buttons::get_instance();
