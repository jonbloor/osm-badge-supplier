<?php
/**
 * Landing page: rewrite, fetch OSM order once, map to cart, render template.
 *
 * @package OSM_Badge_Supplier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the OSM order landing endpoint.
 */
class OSM_Badge_Supplier_Landing {

	const QUERY_VAR = 'osm_badge_order';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_request' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
	}

	/**
	 * Register rewrite rule for the configured landing slug.
	 */
	public static function register_rewrite() {
		$slug = OSM_Badge_Supplier_Settings::get_landing_slug();
		add_rewrite_rule(
			'^' . preg_quote( $slug, '/' ) . '/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Whether the current request is the OSM landing page.
	 *
	 * @return bool
	 */
	public static function is_landing() {
		return (bool) get_query_var( self::QUERY_VAR );
	}

	/**
	 * Enqueue light CSS on the landing page only.
	 */
	public static function maybe_enqueue_assets() {
		if ( ! self::is_landing() ) {
			return;
		}
		wp_enqueue_style(
			'osm-badge-supplier-landing',
			OSM_BADGE_SUPPLIER_URL . 'assets/css/landing.css',
			array(),
			OSM_BADGE_SUPPLIER_VERSION
		);
	}

	/**
	 * Process landing request: require WC session, fetch once, map, render.
	 */
	public static function handle_request() {
		if ( ! self::is_landing() ) {
			return;
		}

		// Ensure WooCommerce session / cart exists for guests.
		if ( function_exists( 'WC' ) ) {
			if ( null === WC()->session ) {
				WC()->initialize_session();
			}
			if ( WC()->session && ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}
			if ( null === WC()->cart ) {
				wc_load_cart();
			}
		}

		$view = array(
			'error'            => null,
			'error_code'       => null,
			'section'          => null,
			'added'            => array(),
			'unavailable'      => array(),
			'dry_run'          => OSM_Badge_Supplier_Settings::is_dry_run(),
			'client_id_empty'  => ( '' === OSM_Badge_Supplier_Settings::get_client_id() ),
			'cart_url'         => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
			'checkout_url'     => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ),
			'has_order_url'    => false,
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OSM inbound GET handshake.
		$raw_order_url = isset( $_GET['order_url'] ) ? wp_unslash( $_GET['order_url'] ) : '';

		/*
		 * order_url arrives URL-encoded in the query string. WordPress / PHP
		 * already decode once into $_GET. Do not re-encode or mutate it —
		 * pass through to the client as the relative path OSM provided.
		 */
		$order_url = is_string( $raw_order_url ) ? $raw_order_url : '';
		$order_url = trim( $order_url );

		if ( '' !== $order_url ) {
			$view['has_order_url'] = true;

			$result = OSM_Badge_Supplier_OSM_Client::fetch_order( $order_url );

			// Intentionally do not store $order_url anywhere (expires ~5 min).

			if ( current_user_can( 'manage_woocommerce' ) ) {
				$view['debug'] = OSM_Badge_Supplier_OSM_Client::get_debug();
			}

			if ( empty( $result['ok'] ) ) {
				$view['error']      = isset( $result['error'] ) ? $result['error'] : __( 'Something went wrong fetching the order from Online Scout Manager.', 'osm-badge-supplier' );
				$view['error_code'] = isset( $result['error_code'] ) ? $result['error_code'] : 'unknown';
			} else {
				$view['section'] = isset( $result['section'] ) ? $result['section'] : null;
				$badges          = isset( $result['badges'] ) && is_array( $result['badges'] ) ? $result['badges'] : array();

				$mapped = OSM_Badge_Supplier_Cart_Mapper::map_and_add( $badges, $view['dry_run'] );
				$view['added']       = $mapped['added'];
				$view['unavailable'] = $mapped['unavailable'];

				if ( empty( $badges ) && empty( $view['error'] ) ) {
					$view['error']      = __( 'Online Scout Manager returned no badges for this order.', 'osm-badge-supplier' );
					$view['error_code'] = 'empty_badges';
				}
			}
		} else {
			$view['error']      = __( 'No order link was provided. Please go back to Online Scout Manager and click through to the shop again.', 'osm-badge-supplier' );
			$view['error_code'] = 'missing_order_url';
		}

		status_header( 200 );
		nocache_headers();

		self::render( $view );
		exit;
	}

	/**
	 * Load the landing template.
	 *
	 * @param array $view Template variables.
	 */
	protected static function render( array $view ) {
		$template = OSM_BADGE_SUPPLIER_PATH . 'templates/landing.php';

		// Expose $view to the template.
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled local scope.
		extract( array( 'view' => $view ), EXTR_SKIP );

		get_header();
		include $template;
		get_footer();
	}
}
