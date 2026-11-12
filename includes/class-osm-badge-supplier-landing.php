<?php
/**
 * Landing page: rewrite, fetch OSM order once, map to cart, render template.
 *
 * @package OSM_Badge_Supplier
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'OSM_Badge_Supplier_Landing', false ) ) {
	return;
}

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
		add_action( 'send_headers', array( __CLASS__, 'deny_page_cache' ), 0 );
		add_action( 'wp', array( __CLASS__, 'deny_page_cache' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_request' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ), 20 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
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
	 * Register the landing query variable.
	 *
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
	 * Landing pages are personalised (cart + order token). Stop full-page caches
	 * such as W3 Total Cache from storing them. The bare /osm-order/ URL was
	 * being stored with a public Cache-Control header because nocache_headers()
	 * alone runs too late for disk page cache.
	 */
	public static function deny_page_cache() {
		global $wp;
		$on_landing = self::is_landing();
		// send_headers runs before WP_Query is filled; the rewrite var is already on $wp.
		if ( ! $on_landing && isset( $wp->query_vars[ self::QUERY_VAR ] ) && $wp->query_vars[ self::QUERY_VAR ] ) {
			$on_landing = true;
		}
		if ( ! $on_landing ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}

	/**
	 * Mark the landing document so cart-drawer CSS can target it.
	 *
	 * The shop header is an Elementor template, but this request is not an
	 * Elementor page, so the body never gets `elementor-default`. Elementor
	 * only hides the side cart with that class.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		if ( self::is_landing() ) {
			$classes[] = 'osm-badge-order';
		}
		return $classes;
	}

	/**
	 * Enqueue landing CSS and the cart/loading script.
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

		$deps = array();
		if ( wp_script_is( 'jquery', 'registered' ) ) {
			$deps[] = 'jquery';
		}
		wp_enqueue_script(
			'osm-badge-supplier-landing',
			OSM_BADGE_SUPPLIER_URL . 'assets/js/landing.js',
			$deps,
			OSM_BADGE_SUPPLIER_VERSION,
			true
		);
		wp_localize_script(
			'osm-badge-supplier-landing',
			'osmBadgeLanding',
			array(
				'nonce'     => wp_create_nonce( self::fetch_nonce_action() ),
				'i18nError' => __( 'We could not load this badge order. Please go back to Online Scout Manager and try again.', 'osm-badge-supplier' ),
			)
		);
	}

	/**
	 * Make sure a WooCommerce session and cart exist for guests.
	 */
	protected static function ensure_cart() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
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

	/**
	 * Whether this is the background order fetch (shop shell already painted).
	 *
	 * @return bool
	 */
	protected static function is_fetch_request() {
		if ( ! isset( $_POST['osm_badge_fetch'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- flag only; the fetch nonce is verified in handle_request() before the order is loaded.
			return false;
		}
		$flag = sanitize_text_field( wp_unslash( $_POST['osm_badge_fetch'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- flag only; the fetch nonce is verified in handle_request() before the order is loaded.
		return '1' === $flag;
	}

	/**
	 * Order URL from OSM. Prefer the POST body on the background fetch.
	 *
	 * The value arrives URL-encoded. WordPress / PHP already decode once.
	 * Do not re-encode or mutate it, and do not store it (the token expires in minutes).
	 * sanitize_text_field() strips every %HH sequence, so the path is validated
	 * by OSM_Badge_Supplier_OSM_Client::is_valid_order_url() instead.
	 *
	 * @return string
	 */
	protected static function read_order_url() {
		$raw = '';
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- OSM token. sanitize_text_field() would strip %HH sequences. Validated by is_valid_order_url(); the fetch nonce is checked before a POST is used.
		if ( isset( $_POST['order_url'] ) ) {
			$raw = wp_unslash( $_POST['order_url'] );
		} elseif ( isset( $_GET['order_url'] ) ) {
			$raw = wp_unslash( $_GET['order_url'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return is_string( $raw ) ? trim( $raw ) : '';
	}

	/**
	 * Nonce action for the background fetch and the no-JavaScript link.
	 *
	 * Tied to the WooCommerce customer session so logged-out visitors do not
	 * share one nonce.
	 *
	 * @return string
	 */
	protected static function fetch_nonce_action() {
		return 'osm_badge_supplier_fetch|' . OSM_Badge_Supplier_Settings::customer_session_token();
	}

	/**
	 * Empty view used before OSM responds.
	 *
	 * @return array
	 */
	protected static function empty_view() {
		return array(
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
			'show_admin_debug' => false,
			'loading'          => false,
			'continue_url'     => home_url( '/' ),
			'sync_url'         => '',
		);
	}

	/**
	 * Fetch OSM and map the cart. Does not store order_url.
	 *
	 * @param string $order_url Relative OSM path, or ''.
	 * @return array
	 */
	protected static function build_view( $order_url ) {
		$view      = self::empty_view();
		$order_url = is_string( $order_url ) ? $order_url : '';

		if ( '' !== $order_url ) {
			$view['has_order_url'] = true;

			$result = OSM_Badge_Supplier_OSM_Client::fetch_order( $order_url );

			$view['show_admin_debug'] = OSM_Badge_Supplier_Settings::is_admin_debug() && current_user_can( 'manage_woocommerce' );
			if ( $view['show_admin_debug'] ) {
				$view['debug'] = OSM_Badge_Supplier_OSM_Client::get_debug();
			}

			if ( empty( $result['ok'] ) ) {
				$view['error']      = isset( $result['error'] ) ? $result['error'] : __( 'Something went wrong fetching the order from Online Scout Manager.', 'osm-badge-supplier' );
				$view['error_code'] = isset( $result['error_code'] ) ? $result['error_code'] : 'unknown';
			} else {
				$view['section'] = isset( $result['section'] ) ? $result['section'] : null;
				$badges          = isset( $result['badges'] ) && is_array( $result['badges'] ) ? $result['badges'] : array();

				$mapped              = OSM_Badge_Supplier_Cart_Mapper::map_and_add( $badges, $view['dry_run'] );
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

		$view['continue_url'] = self::continue_shopping_url( $view['section'] );
		return $view;
	}

	/**
	 * Process landing request.
	 *
	 * With an order_url, the shop shell is sent immediately and the OSM POST
	 * runs on a second request. Otherwise the browser stays blank until
	 * wp_remote_post returns (timeout 20 seconds). osm_sync=1 keeps that
	 * blocking render for browsers without JavaScript.
	 */
	public static function handle_request() {
		if ( ! self::is_landing() ) {
			return;
		}

		self::ensure_cart();

		if ( self::is_fetch_request() ) {
			nocache_headers();
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
			if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::fetch_nonce_action() ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'This page has expired. Please go back to Online Scout Manager and open the shop again.', 'osm-badge-supplier' ),
					),
					403
				);
			}
			$view = self::build_view( self::read_order_url() );
			wp_send_json_success(
				array(
					'html'        => self::capture_results( $view ),
					'unavailable' => count( $view['unavailable'] ),
					'added'       => count( $view['added'] ),
				)
			);
		}

		$view = self::view_for_page();

		status_header( 200 );
		nocache_headers();

		self::render( $view );
		exit;
	}

	/**
	 * Landing view for a full page load, including the no-JavaScript sync link.
	 *
	 * The osm_sync=1 flag fetches only when osm_fetch_nonce matches this session.
	 * A missing or wrong nonce does not call OSM.
	 *
	 * @return array
	 */
	protected static function view_for_page() {
		$order_url = self::read_order_url();
		$is_sync   = isset( $_GET['osm_sync'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['osm_sync'] ) );

		$loading = ( '' !== $order_url && ! $is_sync );
		if ( $loading ) {
			$view             = self::empty_view();
			$view['loading']  = true;
			$view['sync_url'] = wp_nonce_url( add_query_arg( 'osm_sync', '1' ), self::fetch_nonce_action(), 'osm_fetch_nonce' );
		} elseif ( $is_sync ) {
			$sync_nonce = isset( $_GET['osm_fetch_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['osm_fetch_nonce'] ) ) : '';
			if ( '' === $sync_nonce || ! wp_verify_nonce( $sync_nonce, self::fetch_nonce_action() ) ) {
				$view                  = self::empty_view();
				$view['error']         = __( 'This page has expired. Please go back to Online Scout Manager and open the shop again.', 'osm-badge-supplier' );
				$view['error_code']    = 'expired';
				$view['has_order_url'] = ( '' !== $order_url );
				$view['loading']       = false;
			} else {
				$view            = self::build_view( $order_url );
				$view['loading'] = false;
			}
		} else {
			$view            = self::build_view( $order_url );
			$view['loading'] = false;
		}

		return $view;
	}

	/**
	 * Render the results partial to a string (background fetch payload).
	 *
	 * @param array $view Template variables.
	 * @return string
	 */
	protected static function capture_results( array $view ) {
		if ( ! is_array( $view ) ) {
			return '';
		}
		$template = OSM_BADGE_SUPPLIER_PATH . 'templates/landing-results.php';
		ob_start();
		include $template;
		return (string) ob_get_clean();
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

	/**
	 * Section key (squirrels/beavers/cubs/scouts/explorers) from OSM section data.
	 *
	 * @param mixed $section OSM section (string or array).
	 * @return string Key or ''.
	 */
	public static function section_key( $section ) {
		$texts = array();
		if ( is_string( $section ) ) {
			$texts[] = $section;
		} elseif ( is_array( $section ) ) {
			foreach ( array( 'type', 'section', 'sectiontype', 'section_type', 'name', 'sectionname', 'groupname' ) as $k ) {
				if ( isset( $section[ $k ] ) && is_scalar( $section[ $k ] ) ) {
					$texts[] = (string) $section[ $k ];
				}
			}
		}
		$text = strtolower( implode( ' ', $texts ) );

		// Order matters: "Explorer Scouts", "Cub Scouts", "Beaver Scouts" all contain "scout".
		$patterns = array(
			'explorers' => '/\bexplorers?\b/',
			'squirrels' => '/\bsquirrels?\b/',
			'beavers'   => '/\bbeavers?\b/',
			'cubs'      => '/\bcubs?\b/',
			'scouts'    => '/\bscouts?\b/',
		);
		$key      = '';
		foreach ( $patterns as $k => $re ) {
			// Scout Network / Scout Active Support are not the Scouts section.
			if ( 'scouts' === $k && ( preg_match( '/\bnetwork\b/i', $text ) || preg_match( '/\bactive\s+support\b/i', $text ) ) ) {
				break;
			}
			if ( preg_match( $re, $text ) ) {
				$key = $k;
				break;
			}
		}
		return (string) apply_filters( 'osm_badge_supplier_section_key', $key, $section );
	}

	/**
	 * Continue shopping URL: category for the order's section, else Shop, else home.
	 *
	 * Prefers the section slug under the "badges" parent (e.g. /product-category/badges/beavers/),
	 * then any category with that slug, then by name.
	 *
	 * @param mixed $section OSM section.
	 * @return string
	 */
	public static function continue_shopping_url( $section ) {
		$key = self::section_key( $section );
		$url = '';

		if ( '' !== $key && taxonomy_exists( 'product_cat' ) ) {
			$map    = apply_filters(
				'osm_badge_supplier_section_category_slugs',
				array(
					'squirrels' => array( 'squirrels', 'squirrel' ),
					'beavers'   => array( 'beavers', 'beaver' ),
					'cubs'      => array( 'cubs', 'cub' ),
					'scouts'    => array( 'scouts', 'scout' ),
					'explorers' => array( 'explorers', 'explorer' ),
				)
			);
			$slugs  = isset( $map[ $key ] ) ? (array) $map[ $key ] : array( $key );
			$parent = get_term_by( 'slug', (string) apply_filters( 'osm_badge_supplier_badges_parent_slug', 'badges' ), 'product_cat' );
			$term   = null;

			if ( $parent && ! is_wp_error( $parent ) ) {
				foreach ( $slugs as $slug ) {
					$found = get_terms(
						array(
							'taxonomy'   => 'product_cat',
							'slug'       => $slug,
							'parent'     => (int) $parent->term_id,
							'hide_empty' => false,
							'number'     => 1,
						)
					);
					if ( ! is_wp_error( $found ) && $found ) {
						$term = $found[0];
						break;
					}
				}
			}
			if ( ! $term ) {
				foreach ( $slugs as $slug ) {
					$t = get_term_by( 'slug', $slug, 'product_cat' );
					if ( $t && ! is_wp_error( $t ) ) {
						$term = $t;
						break;
					}
				}
			}
			if ( ! $term ) {
				$t = get_term_by( 'name', ucfirst( $key ), 'product_cat' );
				if ( $t && ! is_wp_error( $t ) ) {
					$term = $t;
				}
			}
			if ( $term ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					$url = $link;
				}
			}
		}

		if ( '' === $url && function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 ) {
			$url = wc_get_page_permalink( 'shop' );
		}
		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		return (string) apply_filters( 'osm_badge_supplier_continue_shopping_url', $url, $key, $section );
	}
}
