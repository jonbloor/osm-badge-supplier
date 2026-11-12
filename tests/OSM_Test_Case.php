<?php
/**
 * Shared fixture reset.
 *
 * @package OSM_Badge_Supplier
 */

/**
 * Base case. Clears the stub globals between tests.
 */
abstract class OSM_Test_Case extends PHPUnit\Framework\TestCase {

	/**
	 * Reset stub state.
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['osm_test_options']    = array();
		$GLOBALS['osm_test_http']       = array();
		$GLOBALS['osm_test_transients'] = array();
		$GLOBALS['osm_test_user_id']    = 0;
		$GLOBALS['osm_test_caps']       = array();
		$GLOBALS['osm_test_filters']    = array();
		$GLOBALS['osm_test_hooks']      = array(
			'filter' => array(),
			'action' => array(),
		);
		$GLOBALS['osm_test_nonces']     = array();
		$GLOBALS['osm_test_notices']    = array();
		$GLOBALS['osm_test_redirect']   = null;
		$GLOBALS['osm_test_referer']    = false;
		$GLOBALS['osm_test_products']   = array();
		$GLOBALS['osm_test_sku_ids']    = array();
		unset( $GLOBALS['osm_test_http_response'] );

		$wc            = new stdClass();
		$wc->cart      = null;
		$wc->session   = null;
		$GLOBALS['osm_test_wc'] = $wc;

		$GLOBALS['wpdb']->results   = array();
		$GLOBALS['wpdb']->last_sql  = '';
		$GLOBALS['wpdb']->last_args = array();

		$_POST                    = array();
		$_GET                     = array();
		$_REQUEST                 = array();
		$_SERVER['REQUEST_URI']   = '/cart/';

		$property = new ReflectionProperty( 'OSM_Badge_Supplier_OSM_Client', 'request_debug' );
		$property->setAccessible( true );
		$property->setValue( null, null );

		$rendered = new ReflectionProperty( 'OSM_Badge_Supplier_Empty_Basket', 'auto_rendered' );
		$rendered->setAccessible( true );
		$rendered->setValue( null, false );
	}

	/**
	 * Call a non-public static method.
	 *
	 * @param string $class  Class.
	 * @param string $method Method.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	protected function invoke( $class, $method, array $args = array() ) {
		$reflection = new ReflectionMethod( $class, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( null, $args );
	}
}
