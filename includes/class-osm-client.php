<?php
/**
 * OSM API client — one-shot POST with client_id in body only.
 *
 * @package OSM_Badge_Supplier
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'OSM_Badge_Supplier_OSM_Client', false ) ) {
	return;
}

/**
 * Fetches badge order details from Online Scout Manager.
 */
class OSM_Badge_Supplier_OSM_Client {

	const DEBUG_TRANSIENT = 'osm_badge_supplier_last_http_debug';

	/**
	 * Fetch order from OSM.
	 *
	 * Protocol (strict):
	 * - Concatenate host + order_url exactly (no mutate/reorder/re-encode of order_url).
	 * - POST application/x-www-form-urlencoded with ONLY client_id in body (direct mode).
	 * - Optional HTTPS relay (Cloudflare Worker) when origin IP is blocked by OSM.
	 * - Never put client_id in the URL.
	 * - Never store order_url for later (token expires ~5 min).
	 *
	 * @param string $order_url Relative path from OSM, e.g. /v3/badges/suppliers/order/123?...
	 * @return array{
	 *   ok: bool,
	 *   error?: string,
	 *   error_code?: string,
	 *   data?: array,
	 *   badges?: array,
	 *   section?: mixed,
	 *   debug?: array
	 * }
	 */
	public static function fetch_order( $order_url ) {
		$order_url = (string) $order_url;

		if ( '' === $order_url ) {
			return array(
				'ok'         => false,
				'error_code' => 'missing_order_url',
				'error'      => __( 'No order link was provided. Please go back to Online Scout Manager and click through to the shop again.', 'osm-badge-supplier' ),
			);
		}

		// Must be a relative path starting with /.
		if ( 0 !== strpos( $order_url, '/' ) ) {
			return array(
				'ok'         => false,
				'error_code' => 'invalid_order_url',
				'error'      => __( 'The order link looks invalid. Please go back to Online Scout Manager and click through again.', 'osm-badge-supplier' ),
			);
		}

		// Reject absolute URLs / scheme injection.
		if ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $order_url ) || false !== stripos( $order_url, '//' ) ) {
			return array(
				'ok'         => false,
				'error_code' => 'invalid_order_url',
				'error'      => __( 'The order link looks invalid. Please go back to Online Scout Manager and click through again.', 'osm-badge-supplier' ),
			);
		}

		$client_id = OSM_Badge_Supplier_Settings::get_client_id();
		if ( '' === $client_id ) {
			return array(
				'ok'         => false,
				'error_code' => 'missing_client_id',
				'error'      => __( 'This shop is not fully configured for OSM orders yet (missing client ID). Please contact the shop administrator.', 'osm-badge-supplier' ),
			);
		}

		$host = OSM_Badge_Supplier_Settings::get_osm_host();
		if ( 0 !== strpos( $host, 'https://' ) ) {
			return array(
				'ok'         => false,
				'error_code' => 'insecure_host',
				'error'      => __( 'OSM host must use HTTPS. Please contact the shop administrator.', 'osm-badge-supplier' ),
			);
		}

		// Bare host 301s to www with HTML — refuse non-www default host mistakes.
		if ( 'https://onlinescoutmanager.co.uk' === $host ) {
			$host = OSM_BADGE_SUPPLIER_DEFAULT_HOST;
		}

		$relay_url    = OSM_Badge_Supplier_Settings::get_relay_url();
		$relay_secret = OSM_Badge_Supplier_Settings::get_relay_secret();
		$use_relay    = ( '' !== $relay_url );

		$site = home_url( '/' );
		$ua   = 'OSMBadgeSupplier/' . OSM_BADGE_SUPPLIER_VERSION . ' (+' . $site . '; WordPress/' . get_bloginfo( 'version' ) . ')';

		if ( $use_relay ) {
			$request_url = $relay_url;
			$headers     = array(
				'Content-Type'       => 'application/x-www-form-urlencoded',
				'Accept'             => 'application/json',
				'User-Agent'         => $ua,
				'X-OSM-Relay-Secret' => $relay_secret,
			);
			$body = array(
				'client_id' => $client_id,
				'order_url' => $order_url,
			);
		} else {
			// Concatenate only — never mutate, reorder, or re-encode order_url.
			$request_url = $host . $order_url;
			$headers     = array(
				'Content-Type' => 'application/x-www-form-urlencoded',
				'Accept'       => 'application/json',
				'User-Agent'   => $ua,
			);
			$body = array(
				'client_id' => $client_id,
			);
		}

		$response = wp_remote_post(
			$request_url,
			array(
				'timeout'     => 20,
				'redirection' => 0, // Do not follow 301 HTML from bare host.
				'headers'     => $headers,
				'body'        => $body,
			)
		);

		$has_auth_guid = ( false !== strpos( $order_url, '_auth_guid=' ) );

		if ( is_wp_error( $response ) ) {
			self::store_debug(
				array(
					'via'           => $use_relay ? 'relay' : 'direct',
					'wp_error'      => $response->get_error_message(),
					'http_code'     => 0,
					'content_type'  => '',
					'body_prefix'   => '',
					'has_auth_guid' => $has_auth_guid,
					'order_url_len' => strlen( $order_url ),
				)
			);
			return array(
				'ok'         => false,
				'error_code' => 'http_error',
				'error'      => __( 'We could not reach Online Scout Manager. Please go back to OSM and click through to the shop again in a few minutes.', 'osm-badge-supplier' ),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body_raw = (string) wp_remote_retrieve_body( $response );
		$ct   = (string) wp_remote_retrieve_header( $response, 'content-type' );
		$xblk = (string) wp_remote_retrieve_header( $response, 'x-blocked' );

		$prefix = substr( $body_raw, 0, 200 );
		// Strip accidental secrets from debug prefix.
		$prefix_safe = preg_replace( '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '[uuid]', $prefix );

		self::store_debug(
			array(
				'via'           => $use_relay ? 'relay' : 'direct',
				'http_code'     => $code,
				'content_type'  => $ct,
				'x_blocked'     => $xblk,
				'body_prefix'   => $prefix_safe,
				'has_auth_guid' => $has_auth_guid,
				'order_url_len' => strlen( $order_url ),
			)
		);

		$looks_blocked = (
			'true' === strtolower( $xblk )
			|| false !== stripos( $body_raw, 'Online Scout Manager (OSM): Blocked' )
			|| false !== stripos( $body_raw, 'x-blocked' )
			|| ( false !== stripos( $ct, 'text/html' ) && false !== stripos( $body_raw, '<!doctype html>' ) )
		);

		if ( $looks_blocked ) {
			return array(
				'ok'         => false,
				'error_code' => 'osm_blocked',
				'error'      => __( 'The shop server could not fetch this order because Online Scout Manager is blocking the server IP. Please ask OSM to allowlist the shop server, or enable the OSM relay. Shop staff: see WooCommerce → OSM Badge Supplier for debug details.', 'osm-badge-supplier' ),
			);
		}

		$json = json_decode( $body_raw, true );

		if ( ! is_array( $json ) ) {
			return array(
				'ok'         => false,
				'error_code' => 'invalid_json',
				'error'      => __( 'Online Scout Manager returned an unexpected response. Please go back to OSM and click through again.', 'osm-badge-supplier' ),
			);
		}

		// OSM may return status:false for expired token / invalid client_id.
		// error can be a string OR an object: {"message":"...","code":"access-error-3"}.
		$status = isset( $json['status'] ) ? $json['status'] : null;
		if ( false === $status || 'false' === $status || 0 === $status ) {
			$error_raw      = isset( $json['error'] ) ? $json['error'] : null;
			$message        = '';
			$osm_error_code = '';

			if ( is_array( $error_raw ) ) {
				if ( isset( $error_raw['message'] ) && ( is_string( $error_raw['message'] ) || is_numeric( $error_raw['message'] ) ) ) {
					$message = (string) $error_raw['message'];
				}
				if ( isset( $error_raw['code'] ) && ( is_string( $error_raw['code'] ) || is_numeric( $error_raw['code'] ) ) ) {
					$osm_error_code = (string) $error_raw['code'];
				}
			} elseif ( is_string( $error_raw ) || is_numeric( $error_raw ) ) {
				$message = (string) $error_raw;
			}

			if ( '' === $osm_error_code && isset( $json['error_code'] ) && ( is_string( $json['error_code'] ) || is_numeric( $json['error_code'] ) ) ) {
				$osm_error_code = (string) $json['error_code'];
			}

			$message_l = strtolower( trim( $message . ' ' . $osm_error_code ) );

			$looks_expired = (
				false !== strpos( $message_l, 'expired' )
				|| false !== strpos( $message_l, 'token' )
				|| false !== strpos( $message_l, 'do not have access' )
				|| false !== strpos( $message_l, 'access-error' )
				|| 0 === strpos( $osm_error_code, 'access-error' )
				|| 401 === $code
				|| 403 === $code
				|| 410 === $code
			);

			if ( $looks_expired ) {
				return array(
					'ok'         => false,
					'error_code' => 'expired',
					'error'      => __( 'This order link has expired or is no longer valid. Please go back to Online Scout Manager and click through to the shop again.', 'osm-badge-supplier' ),
				);
			}

			if ( false !== strpos( $message_l, 'client' ) || 'invalid_client' === $osm_error_code ) {
				return array(
					'ok'         => false,
					'error_code' => 'invalid_client_id',
					'error'      => __( 'The shop could not authenticate with Online Scout Manager. Please contact the shop administrator, or go back to OSM and try again.', 'osm-badge-supplier' ),
				);
			}

			return array(
				'ok'         => false,
				'error_code' => 'osm_status_false',
				'error'      => __( 'Online Scout Manager could not provide this order. Please go back to OSM and click through to the shop again.', 'osm-badge-supplier' ),
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			if ( 401 === $code || 403 === $code || 410 === $code ) {
				return array(
					'ok'         => false,
					'error_code' => 'expired',
					'error'      => __( 'This order link has expired or is no longer valid. Please go back to Online Scout Manager and click through to the shop again.', 'osm-badge-supplier' ),
				);
			}
			return array(
				'ok'         => false,
				'error_code' => 'http_status',
				'error'      => __( 'Online Scout Manager returned an error. Please go back to OSM and click through again.', 'osm-badge-supplier' ),
			);
		}

		$data   = isset( $json['data'] ) && is_array( $json['data'] ) ? $json['data'] : $json;
		$badges = array();

		if ( isset( $data['badges'] ) && is_array( $data['badges'] ) ) {
			$badges = $data['badges'];
		} elseif ( isset( $json['badges'] ) && is_array( $json['badges'] ) ) {
			$badges = $json['badges'];
		}

		$section = null;
		if ( isset( $data['section'] ) ) {
			$section = $data['section'];
		} elseif ( isset( $json['section'] ) ) {
			$section = $json['section'];
		}

		return array(
			'ok'      => true,
			'data'    => $data,
			'badges'  => $badges,
			'section' => $section,
		);
	}

	/**
	 * Store last HTTP debug for admins (transient). Never stores client_id or full order_url.
	 *
	 * @param array $debug Debug payload.
	 */
	public static function store_debug( array $debug ) {
		set_transient( self::DEBUG_TRANSIENT, $debug, HOUR_IN_SECONDS );
	}

	/**
	 * @return array|null
	 */
	public static function get_debug() {
		$d = get_transient( self::DEBUG_TRANSIENT );
		return is_array( $d ) ? $d : null;
	}
}
