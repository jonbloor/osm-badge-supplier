<?php
/**
 * Cart mapping and the empty-basket handler. No WooCommerce install and no HTTP.
 *
 * @package OSM_Badge_Supplier
 */

/**
 * Mapper and empty-basket permission tests.
 */
class CartAndBasketTest extends OSM_Test_Case {

	/**
	 * Null stock codes are listed, never guessed from the badge name.
	 * A space in the stock code can resolve to a hyphenated SKU.
	 */
	public function test_map_does_not_guess_and_resolves_hyphen_sku() {
		$GLOBALS['wpdb']->results = array(
			array(
				'sku'        => 'ART-1',
				'product_id' => 42,
			),
		);
		$GLOBALS['osm_test_products'][42] = new OSM_Test_Product( 'Artist badge' );
		$cart                             = new OSM_Test_Cart();
		$GLOBALS['osm_test_wc']->cart     = $cart;

		$mapped = OSM_Badge_Supplier_Cart_Mapper::map_and_add(
			array(
				array(
					'name'       => 'Artist',
					'quantity'   => 2,
					'stock_code' => 'ART 1',
				),
				array(
					'name'       => 'Skipped',
					'quantity'   => 0,
					'stock_code' => 'SKIP',
				),
				array(
					'name'       => 'No code',
					'quantity'   => 1,
					'stock_code' => null,
				),
			)
		);

		$this->assertCount( 1, $mapped['added'] );
		$this->assertSame( 'ART-1', $mapped['added'][0]['sku'] );
		$this->assertSame( 42, $mapped['added'][0]['product_id'] );
		$this->assertSame( 'Artist badge', $mapped['added'][0]['product_name'] );
		$this->assertSame( array( array( 42, 2 ) ), $cart->added );

		$this->assertCount( 2, $mapped['unavailable'] );
		$this->assertStringContainsString( 'Quantity is zero', $mapped['unavailable'][0]['reason'] );
		$this->assertStringContainsString( 'No stock code', $mapped['unavailable'][1]['reason'] );
		$this->assertSame( 'No code', $mapped['unavailable'][1]['name'] );
	}

	/**
	 * Dry run reports the product and does not touch the cart.
	 */
	public function test_dry_run_does_not_add() {
		$GLOBALS['osm_test_sku_ids']['ART-1'] = 42;
		$GLOBALS['osm_test_products'][42]     = new OSM_Test_Product( 'Artist badge' );
		$cart                                 = new OSM_Test_Cart();
		$GLOBALS['osm_test_wc']->cart         = $cart;

		$mapped = OSM_Badge_Supplier_Cart_Mapper::map_and_add(
			array(
				array(
					'name'       => 'Artist',
					'quantity'   => 1,
					'stock_code' => 'ART-1',
				),
			),
			true
		);

		$this->assertTrue( $mapped['added'][0]['dry_run'] );
		$this->assertSame( array(), $cart->added );
		$this->assertNull( $mapped['added'][0]['cart_item_key'] );
	}

	/**
	 * Not purchasable, out of stock, and a missing cart are all reported.
	 */
	public function test_unpurchasable_and_missing_cart() {
		$product              = new OSM_Test_Product( 'Paused' );
		$product->purchasable = false;
		$GLOBALS['osm_test_products'][7] = $product;
		$GLOBALS['osm_test_sku_ids']['PAUSE'] = 7;

		$paused = OSM_Badge_Supplier_Cart_Mapper::map_and_add(
			array(
				array(
					'name'       => 'Paused',
					'quantity'   => 1,
					'stock_code' => 'PAUSE',
				),
			)
		);
		$this->assertStringContainsString( 'not purchasable', $paused['unavailable'][0]['reason'] );

		$stocked                       = new OSM_Test_Product( 'Gone' );
		$stocked->in_stock             = false;
		$GLOBALS['osm_test_products'][8] = $stocked;
		$GLOBALS['osm_test_sku_ids']['GONE'] = 8;
		$GLOBALS['osm_test_wc']->cart  = new OSM_Test_Cart();
		$gone = OSM_Badge_Supplier_Cart_Mapper::map_and_add(
			array(
				array(
					'name'       => 'Gone',
					'quantity'   => 1,
					'stock_code' => 'GONE',
				),
			)
		);
		$this->assertStringContainsString( 'out of stock', $gone['unavailable'][0]['reason'] );
	}

	/**
	 * Guests do not see the button. Two sessions do not share a nonce action.
	 */
	public function test_empty_basket_is_admin_only_and_nonce_is_per_session() {
		$cart                         = new OSM_Test_Cart();
		$GLOBALS['osm_test_wc']->cart = $cart;
		$this->assertSame( '', OSM_Badge_Supplier_Empty_Basket::render_button( array( 'force' => true ) ) );

		$GLOBALS['osm_test_wc']->session = new OSM_Test_Session( 'guest-a' );
		$first                           = $this->invoke( 'OSM_Badge_Supplier_Empty_Basket', 'nonce_action' );
		$GLOBALS['osm_test_wc']->session = new OSM_Test_Session( 'guest-b' );
		$second                          = $this->invoke( 'OSM_Badge_Supplier_Empty_Basket', 'nonce_action' );

		$this->assertStringStartsWith( 'osm_empty_basket|', $first );
		$this->assertNotSame( $first, $second );
	}

	/**
	 * The public filter is the only way a guest can render the button.
	 */
	public function test_public_filter_renders_for_a_guest_with_items() {
		$GLOBALS['osm_test_filters']['osm_badge_supplier_empty_basket_public'] = '__return_test_true';
		$GLOBALS['osm_test_wc']->session = new OSM_Test_Session( 'guest-a' );
		$cart                            = new OSM_Test_Cart();
		$GLOBALS['osm_test_wc']->cart    = $cart;

		$html = OSM_Badge_Supplier_Empty_Basket::render_button();
		$this->assertStringContainsString( 'osm_empty_basket', $html );
		$this->assertStringContainsString( 'osm_empty_basket_nonce', $html );
	}

	/**
	 * A shopper who is not a manager is refused before the cart is emptied.
	 */
	public function test_empty_basket_refuses_without_capability() {
		$cart                         = new OSM_Test_Cart();
		$GLOBALS['osm_test_wc']->cart = $cart;
		$_POST['osm_badge_action']    = 'osm_empty_basket';

		try {
			OSM_Badge_Supplier_Empty_Basket::handle_request();
			$this->fail( 'Expected wp_die.' );
		} catch ( OSM_Test_Die $die ) {
			$this->assertSame( 403, $die->status );
		}
		$this->assertFalse( $cart->emptied );
	}

	/**
	 * A valid manager with a bad nonce does not empty the cart.
	 */
	public function test_empty_basket_refuses_a_bad_nonce() {
		$this->act_as_manager( 'manager-1' );
		$_POST['osm_badge_action'] = 'osm_empty_basket';
		$_POST['osm_empty_basket_nonce'] = 'not-the-nonce';

		try {
			OSM_Badge_Supplier_Empty_Basket::handle_request();
			$this->fail( 'Expected wp_die.' );
		} catch ( OSM_Test_Die $die ) {
			$this->assertSame( 403, $die->status );
		}
		$this->assertFalse( $GLOBALS['osm_test_wc']->cart->emptied );
	}

	/**
	 * A good nonce empties the cart and will not send the leader back to an order link
	 * or off the shop.
	 */
	public function test_empty_basket_redirects_safely() {
		$this->act_as_manager( 'manager-1' );
		$action = $this->invoke( 'OSM_Badge_Supplier_Empty_Basket', 'nonce_action' );
		$_POST['osm_badge_action']       = 'osm_empty_basket';
		$_POST['osm_empty_basket_nonce'] = wp_create_nonce( $action );
		$_POST['osm_redirect']           = 'https://shop.test/osm-order/?order_url=secret';

		try {
			OSM_Badge_Supplier_Empty_Basket::handle_request();
			$this->fail( 'Expected a redirect.' );
		} catch ( OSM_Test_Redirect $redirect ) {
			$this->assertSame( 'https://shop.test/cart/', $redirect->getMessage() );
		}
		$this->assertTrue( $GLOBALS['osm_test_wc']->cart->emptied );

		$GLOBALS['osm_test_wc']->cart->emptied = false;
		$_POST['osm_redirect']                 = 'https://evil.test/phish';
		try {
			OSM_Badge_Supplier_Empty_Basket::handle_request();
			$this->fail( 'Expected a redirect.' );
		} catch ( OSM_Test_Redirect $redirect ) {
			$this->assertSame( 'https://shop.test/cart/', $redirect->getMessage() );
		}
	}

	/**
	 * Markup from a shortcode attribute is escaped.
	 */
	public function test_button_escapes_attributes() {
		$this->act_as_manager( 'manager-1' );
		$html = OSM_Badge_Supplier_Empty_Basket::render_button(
			array(
				'label'    => '<script>alert(1)</script>',
				'class'    => 'ok "><img src=x onerror=alert(1)>',
				'redirect' => 'javascript:alert(1)',
				'confirm'  => 'Remove "everything"?',
				'force'    => true,
			)
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringContainsString( 'ok', $html );
		$this->assertStringContainsString( 'https://shop.test/cart/', $html );
	}

	/**
	 * @param string $session_id Session id.
	 */
	private function act_as_manager( $session_id ) {
		$GLOBALS['osm_test_user_id'] = 4;
		$GLOBALS['osm_test_caps']['manage_woocommerce'] = true;
		$GLOBALS['osm_test_wc']->session = new OSM_Test_Session( $session_id );
		$GLOBALS['osm_test_wc']->cart    = new OSM_Test_Cart();
	}
}

/**
 * Filter callback. Named, so it can be passed as a string.
 *
 * @return bool
 */
function __return_test_true() {
	return true;
}
