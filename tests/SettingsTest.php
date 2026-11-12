<?php
/**
 * Settings sanitising. No database and no HTTP.
 *
 * @package OSM_Badge_Supplier
 */

/**
 * Settings sanitizers and the session token used by nonces.
 */
class SettingsTest extends OSM_Test_Case {

	/**
	 * Host must stay HTTPS, and the bare OSM host is rewritten to www.
	 */
	public function test_sanitize_host() {
		$this->assertSame(
			OSM_BADGE_SUPPLIER_DEFAULT_HOST,
			OSM_Badge_Supplier_Settings::sanitize_host( 'http://evil.test/osm' )
		);
		$this->assertSame(
			OSM_BADGE_SUPPLIER_DEFAULT_HOST,
			OSM_Badge_Supplier_Settings::sanitize_host( 'javascript:alert(1)' )
		);
		$this->assertSame(
			OSM_BADGE_SUPPLIER_DEFAULT_HOST,
			OSM_Badge_Supplier_Settings::sanitize_host( 'https://onlinescoutmanager.co.uk/' )
		);
		$this->assertSame(
			'https://example.test/osm',
			OSM_Badge_Supplier_Settings::sanitize_host( 'https://example.test/osm/' )
		);
	}

	/**
	 * A stored http host is not used.
	 */
	public function test_get_osm_host_rejects_insecure_option() {
		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_HOST ] = 'http://evil.test';
		$this->assertSame( OSM_BADGE_SUPPLIER_DEFAULT_HOST, OSM_Badge_Supplier_Settings::get_osm_host() );
	}

	/**
	 * Relay URL is HTTPS or empty. A missing field does not clear a saved URL.
	 */
	public function test_sanitize_relay_url() {
		$this->assertSame( '', OSM_Badge_Supplier_Settings::sanitize_relay_url( '' ) );
		$this->assertSame( '', OSM_Badge_Supplier_Settings::sanitize_relay_url( 'http://relay.test/hook' ) );
		$this->assertSame( '', OSM_Badge_Supplier_Settings::sanitize_relay_url( 'javascript:alert(1)' ) );
		$this->assertSame(
			'https://relay.test/hook',
			OSM_Badge_Supplier_Settings::sanitize_relay_url( 'https://relay.test/hook/' )
		);

		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_RELAY_URL ] = 'https://relay.test/saved';
		$this->assertSame( 'https://relay.test/saved', OSM_Badge_Supplier_Settings::sanitize_relay_url( null ) );
	}

	/**
	 * An omitted secret is kept. An empty submission clears it.
	 */
	public function test_secret_fields_keep_stored_value_when_absent() {
		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_CLIENT_ID ]    = 'stored-client';
		$GLOBALS['osm_test_options'][ OSM_Badge_Supplier_Settings::OPTION_RELAY_SECRET ] = 'stored-secret';

		$this->assertSame( 'stored-client', OSM_Badge_Supplier_Settings::sanitize_client_id( null ) );
		$this->assertSame( 'stored-secret', OSM_Badge_Supplier_Settings::sanitize_relay_secret( null ) );
		$this->assertSame( '', OSM_Badge_Supplier_Settings::sanitize_client_id( '' ) );
		$this->assertSame( '', OSM_Badge_Supplier_Settings::sanitize_relay_secret( array( 'nope' ) ) );
		$this->assertSame( 'new-client', OSM_Badge_Supplier_Settings::sanitize_client_id( '  new-client  ' ) );
	}

	/**
	 * Slug falls back to the default and cannot contain a slash.
	 */
	public function test_sanitize_landing_slug() {
		$this->assertSame( 'osm-order', OSM_Badge_Supplier_Settings::sanitize_landing_slug( '' ) );
		$this->assertSame( 'osm-order', OSM_Badge_Supplier_Settings::sanitize_landing_slug( 'OSM Order' ) );
		$this->assertSame( 'admin', OSM_Badge_Supplier_Settings::sanitize_landing_slug( '../admin' ) );
	}

	/**
	 * Checkboxes are only yes or no.
	 */
	public function test_sanitize_checkbox() {
		$this->assertSame( 'yes', OSM_Badge_Supplier_Settings::sanitize_checkbox( 'yes' ) );
		$this->assertSame( 'yes', OSM_Badge_Supplier_Settings::sanitize_checkbox( '1' ) );
		$this->assertSame( 'yes', OSM_Badge_Supplier_Settings::sanitize_checkbox( true ) );
		$this->assertSame( 'yes', OSM_Badge_Supplier_Settings::sanitize_checkbox( 1 ) );
		$this->assertSame( 'no', OSM_Badge_Supplier_Settings::sanitize_checkbox( 'no' ) );
		$this->assertSame( 'no', OSM_Badge_Supplier_Settings::sanitize_checkbox( null ) );
		$this->assertSame( 'no', OSM_Badge_Supplier_Settings::sanitize_checkbox( 'true' ) );
	}

	/**
	 * options.php allows shop managers on this group and no other.
	 */
	public function test_option_page_capability_is_only_lowered_for_this_group() {
		$this->assertSame(
			'manage_woocommerce',
			OSM_Badge_Supplier_Settings::capability_for_option_page( 'osm_badge_supplier_settings', 'manage_options' )
		);
		$this->assertSame(
			'manage_woocommerce',
			OSM_Badge_Supplier_Settings::capability_for_option_page( OSM_Badge_Supplier_Settings::SETTINGS_GROUP )
		);
		$this->assertSame(
			'manage_options',
			OSM_Badge_Supplier_Settings::capability_for_option_page( 'general', 'manage_options' )
		);
		$this->assertSame(
			'manage_options',
			OSM_Badge_Supplier_Settings::capability_for_option_page( 'woocommerce', 'manage_options' )
		);
		$this->assertSame(
			'manage_network_options',
			OSM_Badge_Supplier_Settings::capability_for_option_page( 'writing', 'manage_network_options' )
		);

		OSM_Badge_Supplier_Settings::init();
		$filters = $GLOBALS['osm_test_hooks']['filter'];
		$ours    = 'option_page_capability_' . OSM_Badge_Supplier_Settings::SETTINGS_GROUP;

		$this->assertArrayHasKey( $ours, $filters );
		$this->assertSame(
			'manage_woocommerce',
			call_user_func( $filters[ $ours ][0], 'manage_options' )
		);

		foreach ( array_keys( $filters ) as $hook ) {
			if ( 0 === strpos( $hook, 'option_page_capability_' ) ) {
				$this->assertSame( $ours, $hook );
			}
		}
		$this->assertArrayNotHasKey( 'option_page_capability_general', $filters );
	}

	/**
	 * Two guests do not share a customer token.
	 */
	public function test_customer_session_token_follows_the_session() {
		$GLOBALS['osm_test_wc']->session = new OSM_Test_Session( 'guest-a' );
		$this->assertSame( 'guest-a', OSM_Badge_Supplier_Settings::customer_session_token() );

		$GLOBALS['osm_test_wc']->session = new OSM_Test_Session( 'guest-b' );
		$this->assertSame( 'guest-b', OSM_Badge_Supplier_Settings::customer_session_token() );

		$GLOBALS['osm_test_wc']->session = null;
		$this->assertSame( '', OSM_Badge_Supplier_Settings::customer_session_token() );
	}
}
