<?php
/**
 * OSM client. HTTP is stubbed; these tests must not reach onlinescoutmanager.co.uk.
 *
 * @package OSM_Badge_Supplier
 */

/**
 * Order URL checks and the fetch client.
 */
class ClientTest extends OSM_Test_Case {

	/**
	 * Paths the plugin and the relay both accept or reject.
	 * Keep the list aligned with osm-relay-worker/worker.test.js.
	 */
	public function test_order_url_rules() {
		$valid = array(
			'/v3/badges/suppliers/order/abc',
			'/v3/badges/suppliers/order/abc?_auth_guid=11111111-2222-3333-4444-555555555555',
			'/v3/badges/suppliers/order/tok%2Fpart',
		);
		$invalid = array(
			'',
			'/etc/passwd',
			'https://www.onlinescoutmanager.co.uk/v3/badges/suppliers/order/abc',
			'//www.onlinescoutmanager.co.uk/v3/badges/suppliers/order/abc',
			'/v3/badges/suppliers/order/../../secret',
			'/v3/badges/suppliers/order/%2e%2e/secret',
			'/v3/badges/suppliers/order/%2f%2fsecret',
			'/v3/badges/suppliers/order/%5csecret',
			"/v3/badges/suppliers/order/abc\nSet-Cookie: x",
			'/v3/badges/suppliers/order/',
			'/v3/badges/suppliers/order/abc\\evil',
		);

		foreach ( $valid as $url ) {
			$this->assertTrue( OSM_Badge_Supplier_OSM_Client::is_valid_order_url( $url ), $url );
		}
		foreach ( $invalid as $url ) {
			$this->assertFalse( OSM_Badge_Supplier_OSM_Client::is_valid_order_url( $url ), $url );
		}
	}

	/**
	 * The order path is not rewritten, including percent-encoding.
	 */
	public function test_read_order_url_does_not_strip_percent_encoding() {
		$_GET['order_url'] = '/v3/badges/suppliers/order/tok%2Fpart?_auth_guid=abc';
		$this->assertSame(
			'/v3/badges/suppliers/order/tok%2Fpart?_auth_guid=abc',
			$this->invoke( 'OSM_Badge_Supplier_Landing', 'read_order_url' )
		);
	}

	/**
	 * A bad path never becomes an HTTP request.
	 */
	public function test_invalid_order_url_does_not_call_http() {
		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_CLIENT_ID ] = 'client-secret-value';
		$result = OSM_Badge_Supplier_OSM_Client::fetch_order( '/v3/badges/suppliers/order/../../secret' );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'invalid_order_url', $result['error_code'] );
		$this->assertSame( array(), $GLOBALS['osm_test_http'] );
	}

	/**
	 * Missing client ID stops before HTTP.
	 */
	public function test_missing_client_id_does_not_call_http() {
		$result = OSM_Badge_Supplier_OSM_Client::fetch_order( '/v3/badges/suppliers/order/abc?_auth_guid=1' );
		$this->assertSame( 'missing_client_id', $result['error_code'] );
		$this->assertSame( array(), $GLOBALS['osm_test_http'] );
	}

	/**
	 * Direct mode posts client_id in the body only, and does not follow redirects.
	 */
	public function test_direct_post_keeps_client_id_out_of_the_url() {
		$this->stub_ok_response();
		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_CLIENT_ID ] = 'client-secret-value';

		$order  = '/v3/badges/suppliers/order/abc?_auth_guid=11111111-2222-3333-4444-555555555555';
		$result = OSM_Badge_Supplier_OSM_Client::fetch_order( $order );

		$this->assertTrue( $result['ok'] );
		$this->assertCount( 1, $result['badges'] );
		$this->assertSame( 'Beavers', $result['section'] );

		$call = $GLOBALS['osm_test_http'][0];
		$this->assertSame( OSM_BADGE_SUPPLIER_DEFAULT_HOST . $order, $call['url'] );
		$this->assertStringNotContainsString( 'client-secret-value', $call['url'] );
		$this->assertSame( array( 'client_id' => 'client-secret-value' ), $call['args']['body'] );
		$this->assertSame( 0, $call['args']['redirection'] );
		$this->assertStringContainsString( 'OSMBadgeSupplier/1.0.14', $call['args']['headers']['User-Agent'] );
	}

	/**
	 * Relay mode sends the secret as a header and the order path in the body.
	 */
	public function test_relay_post_targets_the_relay() {
		$this->stub_ok_response();
		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_CLIENT_ID ]    = 'client-secret-value';
		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_RELAY_URL ]    = 'https://relay.test/hook';
		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_RELAY_SECRET ] = 'relay-secret-value';

		OSM_Badge_Supplier_OSM_Client::fetch_order( '/v3/badges/suppliers/order/abc?_auth_guid=1' );
		$call = $GLOBALS['osm_test_http'][0];

		$this->assertSame( 'https://relay.test/hook', $call['url'] );
		$this->assertStringNotContainsString( 'client-secret-value', $call['url'] );
		$this->assertStringNotContainsString( 'relay-secret-value', $call['url'] );
		$this->assertSame( 'relay-secret-value', $call['args']['headers']['X-OSM-Relay-Secret'] );
		$this->assertSame( 'client-secret-value', $call['args']['body']['client_id'] );
		$this->assertSame( '/v3/badges/suppliers/order/abc?_auth_guid=1', $call['args']['body']['order_url'] );
	}

	/**
	 * An HTML block page is not reported as invalid JSON.
	 */
	public function test_blocked_html_is_osm_blocked() {
		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_CLIENT_ID ] = 'client-secret-value';
		$GLOBALS['osm_test_http_response'] = array(
			'code'    => 403,
			'body'    => '<!doctype html> Online Scout Manager (OSM): Blocked',
			'headers' => array(
				'content-type' => 'text/html',
				'x-blocked'    => 'true',
			),
		);

		$result = OSM_Badge_Supplier_OSM_Client::fetch_order( '/v3/badges/suppliers/order/abc' );
		$this->assertSame( 'osm_blocked', $result['error_code'] );
	}

	/**
	 * Object-shaped OSM errors map to the expired-link message.
	 */
	public function test_access_error_object_is_expired() {
		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_CLIENT_ID ] = 'client-secret-value';
		$GLOBALS['osm_test_http_response'] = array(
			'code'    => 200,
			'body'    => '{"status":false,"error":{"message":"You do not have access","code":"access-error-3"}}',
			'headers' => array(
				'content-type' => 'application/json',
				'x-blocked'    => '',
			),
		);

		$result = OSM_Badge_Supplier_OSM_Client::fetch_order( '/v3/badges/suppliers/order/abc' );
		$this->assertSame( 'expired', $result['error_code'] );
		$this->assertStringNotContainsString( 'access-error-3', $result['error'] );
	}

	/**
	 * Secrets in a response snippet are removed before an admin can see them.
	 */
	public function test_debug_prefix_redacts_secrets_for_the_current_manager_only() {
		$client = 'client-secret-value';
		$secret = 'relay-secret-value';
		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_CLIENT_ID ]    = $client;
		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_RELAY_SECRET ] = $secret;
		$GLOBALS['osm_test_http_response'] = array(
			'code'    => 200,
			'body'    => 'oops ' . $client . ' and ' . $secret . ' _auth_guid=abcdef123456 uuid 11111111-2222-3333-4444-555555555555',
			'headers' => array(
				'content-type' => 'text/plain',
				'x-blocked'    => '',
			),
		);
		$GLOBALS['osm_test_user_id'] = 7;
		$GLOBALS['osm_test_caps']['manage_woocommerce'] = true;

		OSM_Badge_Supplier_OSM_Client::fetch_order( '/v3/badges/suppliers/order/abc?_auth_guid=1' );
		$debug = OSM_Badge_Supplier_OSM_Client::get_debug();

		$this->assertIsArray( $debug );
		$this->assertStringNotContainsString( $client, $debug['body_prefix'] );
		$this->assertStringNotContainsString( $secret, $debug['body_prefix'] );
		$this->assertStringNotContainsString( 'abcdef123456', $debug['body_prefix'] );
		$this->assertStringNotContainsString( '11111111-2222-3333-4444-555555555555', $debug['body_prefix'] );
		$this->assertStringContainsString( '[redacted]', $debug['body_prefix'] );
		$this->assertStringContainsString( '[uuid]', $debug['body_prefix'] );

		$keys = array_keys( $GLOBALS['osm_test_transients'] );
		$this->assertCount( 1, $keys );
		$this->assertStringContainsString( '_7', $keys[0] );
		$this->assertArrayNotHasKey( OSM_Badge_Supplier_OSM_Client::DEBUG_TRANSIENT, $GLOBALS['osm_test_transients'] );
	}

	/**
	 * A customer checkout does not write the debug transient.
	 */
	public function test_customer_fetch_does_not_store_debug() {
		$this->stub_ok_response();
		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_CLIENT_ID ] = 'client-secret-value';

		OSM_Badge_Supplier_OSM_Client::fetch_order( '/v3/badges/suppliers/order/abc' );

		$this->assertSame( array(), $GLOBALS['osm_test_transients'] );
		$this->assertNull( OSM_Badge_Supplier_OSM_Client::get_debug() );
	}

	/**
	 * Section names, including the ones that must not map to Scouts.
	 */
	public function test_section_key() {
		$this->assertSame( 'beavers', OSM_Badge_Supplier_Landing::section_key( 'Beaver Scouts' ) );
		$this->assertSame( 'cubs', OSM_Badge_Supplier_Landing::section_key( 'Cub Scouts' ) );
		$this->assertSame( 'scouts', OSM_Badge_Supplier_Landing::section_key( 'Scouts' ) );
		$this->assertSame( 'explorers', OSM_Badge_Supplier_Landing::section_key( 'Explorer Scouts' ) );
		$this->assertSame( 'squirrels', OSM_Badge_Supplier_Landing::section_key( 'Squirrels' ) );
		$this->assertSame( '', OSM_Badge_Supplier_Landing::section_key( 'Scout Network' ) );
		$this->assertSame( '', OSM_Badge_Supplier_Landing::section_key( 'Scout Active Support' ) );
		$this->assertSame( 'beavers', OSM_Badge_Supplier_Landing::section_key( array( 'name' => '1st Beavers' ) ) );
	}

	/**
	 * With no catalogue terms, continue shopping falls back to the shop home.
	 */
	public function test_continue_shopping_falls_back_home() {
		$this->assertSame( 'https://shop.test/', OSM_Badge_Supplier_Landing::continue_shopping_url( 'Beavers' ) );
		$this->assertSame( 'https://shop.test/', OSM_Badge_Supplier_Landing::continue_shopping_url( 'Scout Network' ) );
	}

	/**
	 * Canned successful OSM payload. Not a live response.
	 */
	private function stub_ok_response() {
		$GLOBALS['osm_test_http_response'] = array(
			'code'    => 200,
			'body'    => '{"status":true,"data":{"section":"Beavers","badges":[{"name":"Artist","quantity":2,"stock_code":"ART 1"}]}}',
			'headers' => array(
				'content-type' => 'application/json',
				'x-blocked'    => '',
			),
		);
	}
}
