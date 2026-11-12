<?php
/**
 * Stubs for the WordPress and WooCommerce functions these tests touch.
 *
 * Nothing here opens a socket. wp_remote_post() records the call and returns
 * a canned response, or throws if a test forgot to set one.
 *
 * @package OSM_Badge_Supplier
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'OSM_BADGE_SUPPLIER_VERSION', '1.0.14' );
define( 'OSM_BADGE_SUPPLIER_FILE', dirname( __DIR__ ) . '/osm-badge-supplier.php' );
define( 'OSM_BADGE_SUPPLIER_PATH', dirname( __DIR__ ) . '/' );
define( 'OSM_BADGE_SUPPLIER_URL', 'https://shop.test/wp-content/plugins/osm-badge-supplier/' );
define( 'OSM_BADGE_SUPPLIER_DEFAULT_HOST', 'https://www.onlinescoutmanager.co.uk' );
define( 'OSM_BADGE_SUPPLIER_DEFAULT_SLUG', 'osm-order' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ARRAY_A', 'ARRAY_A' );

/**
 * WordPress error stand-in.
 */
class OSM_Test_Wp_Error {
	/**
	 * @var string
	 */
	public $message;

	/**
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 */
	public function __construct( $code, $message ) {
		unset( $code );
		$this->message = (string) $message;
	}

	/**
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}
}

/**
 * Product stand-in.
 */
class OSM_Test_Product {
	/**
	 * @var bool
	 */
	public $purchasable = true;

	/**
	 * @var bool
	 */
	public $in_stock = true;

	/**
	 * @var string
	 */
	public $name;

	/**
	 * @param string $name Product name.
	 */
	public function __construct( $name ) {
		$this->name = $name;
	}

	/**
	 * @return bool
	 */
	public function is_purchasable() {
		return $this->purchasable;
	}

	/**
	 * @return bool
	 */
	public function is_in_stock() {
		return $this->in_stock;
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}
}

/**
 * Cart stand-in.
 */
class OSM_Test_Cart {
	/**
	 * @var bool
	 */
	public $empty = false;

	/**
	 * @var bool
	 */
	public $emptied = false;

	/**
	 * @var bool
	 */
	public $fail = false;

	/**
	 * @var array<int,array{0:int,1:int}>
	 */
	public $added = array();

	/**
	 * @return bool
	 */
	public function is_empty() {
		return $this->empty;
	}

	/**
	 * Empty the cart.
	 */
	public function empty_cart() {
		$this->emptied = true;
		$this->empty   = true;
	}

	/**
	 * @param int $product_id Product ID.
	 * @param int $quantity   Quantity.
	 * @return string|false
	 */
	public function add_to_cart( $product_id, $quantity = 1 ) {
		if ( $this->fail ) {
			return false;
		}
		$this->added[] = array( (int) $product_id, (int) $quantity );
		return 'item-' . (int) $product_id;
	}
}

/**
 * Session stand-in.
 */
class OSM_Test_Session {
	/**
	 * @var string
	 */
	public $id;

	/**
	 * @param string $id Customer id.
	 */
	public function __construct( $id ) {
		$this->id = $id;
	}

	/**
	 * @return string
	 */
	public function get_customer_unique_id() {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function get_customer_id() {
		return $this->id;
	}
}

/**
 * Lookup-table stand-in. No database connection.
 */
class OSM_Test_Wpdb {
	/**
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * @var string
	 */
	public $wc_product_meta_lookup = 'wp_wc_product_meta_lookup';

	/**
	 * @var array<int,array<string,mixed>>
	 */
	public $results = array();

	/**
	 * @var string
	 */
	public $last_sql = '';

	/**
	 * @var array<int,mixed>
	 */
	public $last_args = array();

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public function esc_like( $text ) {
		return (string) $text;
	}

	/**
	 * @param string $sql SQL.
	 * @return string
	 */
	public function prepare( $sql ) {
		$args           = func_get_args();
		$this->last_sql = (string) array_shift( $args );
		$this->last_args = $args;
		return $this->last_sql;
	}

	/**
	 * @param string $sql  SQL.
	 * @param string $mode Return mode.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_results( $sql, $mode = ARRAY_A ) {
		unset( $sql, $mode );
		return $this->results;
	}

	/**
	 * @param string $sql SQL.
	 * @return string
	 */
	public function get_var( $sql ) {
		unset( $sql );
		return $this->wc_product_meta_lookup;
	}
}

/**
 * Thrown instead of exiting on wp_die().
 */
class OSM_Test_Die extends Exception {
	/**
	 * @var int
	 */
	public $status;

	/**
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 */
	public function __construct( $message, $status = 0 ) {
		parent::__construct( (string) $message );
		$this->status = (int) $status;
	}
}

/**
 * Thrown instead of exiting on wp_safe_redirect().
 */
class OSM_Test_Redirect extends Exception {}

/**
 * @return object
 */
function WC() {
	return $GLOBALS['osm_test_wc'];
}

/**
 * @param string $text    Text.
 * @param string $domain  Text domain.
 * @return string
 */
function __( $text, $domain = 'default' ) {
	unset( $domain );
	return (string) $text;
}

/**
 * @param string $single Single.
 * @param string $plural Plural.
 * @param int    $number Count.
 * @param string $domain Text domain.
 * @return string
 */
function _n( $single, $plural, $number, $domain = 'default' ) {
	unset( $domain );
	return 1 === (int) $number ? (string) $single : (string) $plural;
}

/**
 * @param string $text Text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * @param string $text Text.
 * @return string
 */
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string
 */
function esc_html__( $text, $domain = 'default' ) {
	unset( $domain );
	return esc_html( $text );
}

/**
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string
 */
function esc_attr__( $text, $domain = 'default' ) {
	unset( $domain );
	return esc_attr( $text );
}

/**
 * @param string $text   Text.
 * @param string $domain Text domain.
 */
function esc_html_e( $text, $domain = 'default' ) {
	unset( $domain );
	echo esc_html( $text );
}

/**
 * @param string $text   Text.
 * @param string $domain Text domain.
 */
function esc_attr_e( $text, $domain = 'default' ) {
	unset( $domain );
	echo esc_attr( $text );
}

/**
 * @param mixed $value Value.
 * @return mixed
 */
function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

/**
 * @param string $text Text.
 * @return string
 */
function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

/**
 * @param mixed $str Value.
 * @return string
 */
function sanitize_text_field( $str ) {
	if ( is_array( $str ) || is_object( $str ) ) {
		return '';
	}
	$str = wp_strip_all_tags( (string) $str );
	$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
	return trim( (string) $str );
}

/**
 * @param string $key Key.
 * @return string
 */
function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return (string) preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

/**
 * @param string $title Title.
 * @return string
 */
function sanitize_title( $title ) {
	$title = strtolower( trim( wp_strip_all_tags( (string) $title ) ) );
	$title = preg_replace( '/[^a-z0-9\-_]+/', '-', $title );
	return trim( (string) $title, '-' );
}

/**
 * @param string $class    Class.
 * @param string $fallback Fallback.
 * @return string
 */
function sanitize_html_class( $class, $fallback = '' ) {
	$class = (string) preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
	if ( '' === $class ) {
		$class = (string) $fallback;
	}
	return $class;
}

/**
 * @param string $value Value.
 * @return string
 */
function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}

/**
 * @param string $url URL.
 * @return string
 */
function esc_url_raw( $url ) {
	$url = trim( (string) $url );
	if ( preg_match( '#^(javascript|data):#i', $url ) ) {
		return '';
	}
	return $url;
}

/**
 * @param string $url URL.
 * @return string
 */
function esc_url( $url ) {
	return htmlspecialchars( esc_url_raw( $url ), ENT_QUOTES, 'UTF-8' );
}

/**
 * @param string $path Path.
 * @return string
 */
function home_url( $path = '' ) {
	$path = (string) $path;
	if ( '' === $path || '/' === $path ) {
		return 'https://shop.test/';
	}
	if ( '/' !== $path[0] ) {
		$path = '/' . $path;
	}
	return 'https://shop.test' . $path;
}

/**
 * @param string $show Field.
 * @return string
 */
function get_bloginfo( $show = '' ) {
	return 'version' === $show ? '6.6' : '';
}

/**
 * @param string $key     Option.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $key, $default = false ) {
	if ( isset( $GLOBALS['osm_test_options'][ $key ] ) ) {
		return $GLOBALS['osm_test_options'][ $key ];
	}
	return $default;
}

/**
 * @param string $key   Key.
 * @param mixed  $value Value.
 * @param int    $ttl   TTL.
 * @return bool
 */
function set_transient( $key, $value, $ttl ) {
	unset( $ttl );
	$GLOBALS['osm_test_transients'][ $key ] = $value;
	return true;
}

/**
 * @param string $key Key.
 * @return mixed
 */
function get_transient( $key ) {
	return isset( $GLOBALS['osm_test_transients'][ $key ] ) ? $GLOBALS['osm_test_transients'][ $key ] : false;
}

/**
 * @return int
 */
function get_current_user_id() {
	return isset( $GLOBALS['osm_test_user_id'] ) ? (int) $GLOBALS['osm_test_user_id'] : 0;
}

/**
 * @return bool
 */
function is_user_logged_in() {
	return get_current_user_id() > 0;
}

/**
 * @param string $cap Capability.
 * @return bool
 */
function current_user_can( $cap ) {
	return ! empty( $GLOBALS['osm_test_caps'][ $cap ] );
}

/**
 * @param string $hook  Hook.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters( $hook, $value ) {
	if ( isset( $GLOBALS['osm_test_filters'][ $hook ] ) && is_callable( $GLOBALS['osm_test_filters'][ $hook ] ) ) {
		return call_user_func( $GLOBALS['osm_test_filters'][ $hook ], $value );
	}
	return $value;
}

/**
 * @param string $taxonomy Taxonomy.
 * @return bool
 */
function taxonomy_exists( $taxonomy ) {
	unset( $taxonomy );
	return false;
}

/**
 * @param string $page Page.
 * @return int
 */
function wc_get_page_id( $page ) {
	unset( $page );
	return 0;
}

/**
 * @return string
 */
function wc_get_cart_url() {
	return 'https://shop.test/cart/';
}

/**
 * @param mixed $thing Thing.
 * @return bool
 */
function is_wp_error( $thing ) {
	return $thing instanceof OSM_Test_Wp_Error;
}

/**
 * @param string $url  URL.
 * @param array  $args Arguments.
 * @return mixed
 */
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['osm_test_http'][] = array(
		'url'  => $url,
		'args' => $args,
	);
	if ( ! isset( $GLOBALS['osm_test_http_response'] ) ) {
		throw new RuntimeException( 'Refusing a live HTTP call. Tests must stub wp_remote_post().' );
	}
	return $GLOBALS['osm_test_http_response'];
}

/**
 * @param mixed $response Response.
 * @return int
 */
function wp_remote_retrieve_response_code( $response ) {
	return ( is_array( $response ) && isset( $response['code'] ) ) ? (int) $response['code'] : 0;
}

/**
 * @param mixed $response Response.
 * @return string
 */
function wp_remote_retrieve_body( $response ) {
	return ( is_array( $response ) && isset( $response['body'] ) ) ? (string) $response['body'] : '';
}

/**
 * @param mixed  $response Response.
 * @param string $header   Header.
 * @return string
 */
function wp_remote_retrieve_header( $response, $header ) {
	$header = strtolower( (string) $header );
	if ( ! is_array( $response ) || ! isset( $response['headers'][ $header ] ) ) {
		return '';
	}
	return (string) $response['headers'][ $header ];
}

/**
 * @param int $id Product ID.
 * @return OSM_Test_Product|null
 */
function wc_get_product( $id ) {
	return isset( $GLOBALS['osm_test_products'][ (int) $id ] ) ? $GLOBALS['osm_test_products'][ (int) $id ] : null;
}

/**
 * @param string $sku SKU.
 * @return int
 */
function wc_get_product_id_by_sku( $sku ) {
	return isset( $GLOBALS['osm_test_sku_ids'][ $sku ] ) ? (int) $GLOBALS['osm_test_sku_ids'][ $sku ] : 0;
}

/**
 * @param string $message Message.
 * @param string $type    Notice type.
 */
function wc_add_notice( $message, $type = 'success' ) {
	$GLOBALS['osm_test_notices'][] = array( (string) $message, (string) $type );
}

/**
 * @param array|string $args     Arguments.
 * @param array        $defaults Defaults.
 * @return array
 */
function wp_parse_args( $args, $defaults = array() ) {
	if ( ! is_array( $args ) ) {
		$args = array();
	}
	return array_merge( $defaults, $args );
}

/**
 * @param array        $pairs    Defaults.
 * @param array|string $atts     Attributes.
 * @param string       $shortcode Shortcode.
 * @return array
 */
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	unset( $shortcode );
	$out = $pairs;
	if ( is_array( $atts ) ) {
		foreach ( $atts as $key => $value ) {
			if ( array_key_exists( $key, $pairs ) ) {
				$out[ $key ] = $value;
			}
		}
	}
	return $out;
}

/**
 * @param string $action Action.
 * @return string
 */
function wp_create_nonce( $action = '-1' ) {
	$nonce = substr( hash( 'sha256', (string) $action ), 0, 10 );
	$GLOBALS['osm_test_nonces'][ (string) $action ] = $nonce;
	return $nonce;
}

/**
 * @param string $nonce  Nonce.
 * @param string $action Action.
 * @return bool
 */
function wp_verify_nonce( $nonce, $action = '-1' ) {
	if ( ! isset( $GLOBALS['osm_test_nonces'][ (string) $action ] ) ) {
		return false;
	}
	$expected = (string) $GLOBALS['osm_test_nonces'][ (string) $action ];
	$nonce    = (string) $nonce;
	if ( strlen( $expected ) !== strlen( $nonce ) ) {
		return false;
	}
	return hash_equals( $expected, $nonce );
}

/**
 * @param string $action  Action.
 * @param string $name    Field name.
 * @param bool   $referer Referer field.
 * @param bool   $echo    Echo.
 * @return string
 */
function wp_nonce_field( $action = '-1', $name = '_wpnonce', $referer = true, $echo = true ) {
	unset( $referer );
	$nonce = wp_create_nonce( $action );
	$html  = '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $nonce ) . '" />';
	if ( $echo ) {
		echo $html;
	}
	return $html;
}

/**
 * @param mixed $data Data.
 * @return string|false
 */
function wp_json_encode( $data ) {
	return json_encode( $data );
}

/**
 * @param string $message Message.
 * @param string $title   Title.
 * @param array  $args    Arguments.
 * @return void
 */
function wp_die( $message = '', $title = '', $args = array() ) {
	unset( $title );
	$status = ( is_array( $args ) && isset( $args['response'] ) ) ? (int) $args['response'] : 0;
	throw new OSM_Test_Die( $message, $status );
}

/**
 * @return string|false
 */
function wp_get_referer() {
	return ! empty( $GLOBALS['osm_test_referer'] ) ? $GLOBALS['osm_test_referer'] : false;
}

/**
 * @param string $location Location.
 * @param string $fallback Fallback.
 * @return string
 */
function wp_validate_redirect( $location, $fallback = '' ) {
	$location = (string) $location;
	if ( 0 === strpos( $location, 'https://shop.test/' ) || 'https://shop.test' === $location ) {
		return $location;
	}
	return (string) $fallback;
}

/**
 * @param string $location Location.
 * @return void
 */
function wp_safe_redirect( $location ) {
	$GLOBALS['osm_test_redirect'] = (string) $location;
	throw new OSM_Test_Redirect( (string) $location );
}

$GLOBALS['wpdb'] = new OSM_Test_Wpdb();

require OSM_BADGE_SUPPLIER_PATH . 'includes/class-osm-badge-supplier-settings.php';
require OSM_BADGE_SUPPLIER_PATH . 'includes/class-osm-badge-supplier-osm-client.php';
require OSM_BADGE_SUPPLIER_PATH . 'includes/class-osm-badge-supplier-cart-mapper.php';
require OSM_BADGE_SUPPLIER_PATH . 'includes/class-osm-badge-supplier-landing.php';
require OSM_BADGE_SUPPLIER_PATH . 'includes/class-osm-badge-supplier-empty-basket.php';

require __DIR__ . '/OSM_Test_Case.php';
