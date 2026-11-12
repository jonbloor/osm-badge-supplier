<?php
/**
 * Landing markup escapes OSM and WooCommerce values.
 *
 * @package OSM_Badge_Supplier
 */

/**
 * Template escaping.
 */
class TemplateTest extends OSM_Test_Case {

	/**
	 * Badge names, reasons, and the debug snippet cannot break out of the HTML.
	 */
	public function test_results_escape_untrusted_text() {
		$GLOBALS['osm_test_user_id'] = 4;
		$GLOBALS['osm_test_caps']['manage_woocommerce'] = true;

		$view = array(
			'error'            => '<script>alert(1)</script>',
			'error_code'       => 'expired',
			'section'          => '<b>Beavers</b>',
			'added'            => array(
				array(
					'product_name' => '<img src=x onerror=alert(1)>',
					'sku'          => 'ART-1',
					'quantity'     => 2,
				),
			),
			'unavailable'      => array(
				array(
					'name'       => '<svg onload=alert(1)>',
					'level'      => '1',
					'stock_code' => '<b>',
					'reason'     => '<script>nope</script>',
					'quantity'   => 1,
				),
			),
			'dry_run'          => false,
			'client_id_empty'  => false,
			'checkout_url'     => 'javascript:alert(1)',
			'continue_url'     => 'https://shop.test/product-category/badges/beavers/',
			'has_order_url'    => true,
			'show_admin_debug' => true,
			'debug'            => array(
				'via'         => 'direct',
				'http_code'   => 200,
				'body_prefix' => '<script>secret</script>',
			),
		);

		$html = $this->render_results( $view );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '<svg', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( 'https://shop.test/product-category/badges/beavers/', $html );
		$this->assertStringContainsString( 'body_prefix:', $html );
	}

	/**
	 * Debug markup stays off unless the view and the capability both allow it.
	 */
	public function test_debug_hidden_without_capability() {
		$view = array(
			'error'            => null,
			'added'            => array(),
			'unavailable'      => array(),
			'dry_run'          => true,
			'show_admin_debug' => true,
			'debug'            => array(
				'body_prefix' => 'hidden-prefix',
			),
			'continue_url'     => 'https://shop.test/',
			'checkout_url'     => 'https://shop.test/checkout/',
		);

		$html = $this->render_results( $view );
		$this->assertStringNotContainsString( 'hidden-prefix', $html );
		$this->assertStringContainsString( 'Dry run is on', $html );
	}

	/**
	 * @param array $view View.
	 * @return string
	 */
	private function render_results( array $view ) {
		ob_start();
		include OSM_BADGE_SUPPLIER_PATH . 'templates/landing-results.php';
		return (string) ob_get_clean();
	}
}
