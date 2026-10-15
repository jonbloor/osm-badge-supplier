<?php
/**
 * "Empty basket" button: nonce-protected POST form with a JS confirm() prompt.
 *
 * Placement options:
 * - Automatically on the /osm-order landing result.
 * - Automatically on classic / shortcode / Elementor carts via woocommerce_before_cart.
 * - Automatically on block carts via render_block (woocommerce/cart).
 * - Anywhere via the [osm_empty_basket] shortcode (recommended for Elementor
 *   page-builder layouts: drop a Shortcode widget where you want the button).
 *
 * Disable the automatic cart-page placement with:
 *   add_filter( 'osm_badge_supplier_auto_cart_empty_button', '__return_false' );
 *
 * @package OSM_Badge_Supplier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Empty basket handler and renderers.
 */
class OSM_Badge_Supplier_Empty_Basket {

	const ACTION = 'osm_empty_basket';
	const NONCE  = 'osm_empty_basket_nonce';

	/**
	 * Whether the automatic cart-page button has already been printed this request.
	 *
	 * @var bool
	 */
	protected static $auto_rendered = false;

	/**
	 * Register hooks.
	 */
	public static function init() {
		// Run after WooCommerce loads the cart (wp_loaded, priority 20 like WC form handlers).
		add_action( 'wp_loaded', array( __CLASS__, 'handle_request' ), 20 );
		add_shortcode( 'osm_empty_basket', array( __CLASS__, 'shortcode' ) );
		// Classic cart template, [woocommerce_cart] shortcode and Elementor Cart widget (outside the cart form).
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'auto_render_classic' ), 20 );
		// Cart block (Gutenberg / block themes).
		add_filter( 'render_block', array( __CLASS__, 'auto_render_block' ), 10, 2 );
	}

	/**
	 * Nonce action tied to this WooCommerce customer session.
	 *
	 * Guests all share user ID 0, so a plain action would give every guest the
	 * same nonce. Adding the WC customer unique id makes it per-session.
	 *
	 * @return string
	 */
	protected static function nonce_action() {
		$customer = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			if ( method_exists( WC()->session, 'get_customer_unique_id' ) ) {
				$customer = (string) WC()->session->get_customer_unique_id();
			} elseif ( method_exists( WC()->session, 'get_customer_id' ) ) {
				$customer = (string) WC()->session->get_customer_id();
			}
		}
		return self::ACTION . '|' . $customer;
	}

	/**
	 * Handle the POSTed empty-basket request.
	 */
	public static function handle_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
		if ( empty( $_POST['osm_badge_action'] ) || self::ACTION !== $_POST['osm_badge_action'] ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::nonce_action() ) ) {
			wp_die(
				esc_html__( 'This link has expired. Please go back, refresh the page and try again.', 'osm-badge-supplier' ),
				esc_html__( 'Empty basket', 'osm-badge-supplier' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Your basket has been emptied.', 'osm-badge-supplier' ), 'success' );
			}
		}

		$fallback = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
		$redirect = isset( $_POST['osm_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['osm_redirect'] ) ) : '';
		if ( '' === $redirect ) {
			$redirect = wp_get_referer() ? wp_get_referer() : $fallback;
		}
		// Never send the user back to an OSM landing URL (the order token has been used).
		if ( false !== strpos( $redirect, 'order_url=' ) ) {
			$redirect = $fallback;
		}

		wp_safe_redirect( wp_validate_redirect( $redirect, $fallback ) );
		exit;
	}

	/**
	 * Current request URL (for redirect back).
	 *
	 * @return string
	 */
	protected static function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return home_url( $uri );
	}

	/**
	 * Render the button HTML.
	 *
	 * @param array $args { label, redirect, class, force }.
	 * @return string
	 */
	public static function render_button( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'label'    => __( 'Empty basket', 'osm-badge-supplier' ),
				'confirm'  => __( 'Remove everything from your basket? This cannot be undone.', 'osm-badge-supplier' ),
				'redirect' => '',
				'class'    => '',
				'force'    => false,
			)
		);

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}
		if ( ! $args['force'] && WC()->cart->is_empty() ) {
			return '';
		}

		$redirect = '' !== $args['redirect'] ? $args['redirect'] : self::current_url();
		if ( false !== strpos( $redirect, 'order_url=' ) && function_exists( 'wc_get_cart_url' ) ) {
			$redirect = wc_get_cart_url();
		}

		$classes = trim( 'osm-empty-basket__btn button ' . $args['class'] );
		$confirm = wp_json_encode( (string) $args['confirm'] );

		$html  = '<form class="osm-empty-basket" method="post" action="' . esc_url( self::current_url() ) . '"';
		$html .= ' onsubmit="return window.confirm(' . esc_attr( $confirm ) . ');" style="display:inline-block;margin:0 0 1em;">';
		$html .= '<input type="hidden" name="osm_badge_action" value="' . esc_attr( self::ACTION ) . '" />';
		$html .= wp_nonce_field( self::nonce_action(), self::NONCE, false, false );
		$html .= '<input type="hidden" name="osm_redirect" value="' . esc_attr( $redirect ) . '" />';
		$html .= '<button type="submit" class="' . esc_attr( $classes ) . '">' . esc_html( $args['label'] ) . '</button>';
		$html .= '</form>';

		return $html;
	}

	/**
	 * [osm_empty_basket label="Empty basket" redirect="" class=""]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'label'    => __( 'Empty basket', 'osm-badge-supplier' ),
				'redirect' => '',
				'class'    => '',
			),
			$atts,
			'osm_empty_basket'
		);
		self::$auto_rendered = true; // Avoid a duplicate automatic button on the same page.
		return self::render_button( $atts );
	}

	/**
	 * Whether automatic cart-page placement is enabled.
	 *
	 * @return bool
	 */
	protected static function auto_enabled() {
		return (bool) apply_filters( 'osm_badge_supplier_auto_cart_empty_button', true );
	}

	/**
	 * Classic / Elementor cart: print once above the cart form.
	 */
	public static function auto_render_classic() {
		if ( self::$auto_rendered || ! self::auto_enabled() ) {
			return;
		}
		$html = self::render_button();
		if ( '' !== $html ) {
			self::$auto_rendered = true;
			echo '<div class="osm-empty-basket-wrap">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render_button().
		}
	}

	/**
	 * Cart block: append the button after the woocommerce/cart block.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public static function auto_render_block( $content, $block ) {
		if ( is_admin() || empty( $block['blockName'] ) || 'woocommerce/cart' !== $block['blockName'] ) {
			return $content;
		}
		if ( self::$auto_rendered || ! self::auto_enabled() ) {
			return $content;
		}
		$html = self::render_button();
		if ( '' === $html ) {
			return $content;
		}
		self::$auto_rendered = true;
		return $content . '<div class="osm-empty-basket-wrap">' . $html . '</div>';
	}
}
