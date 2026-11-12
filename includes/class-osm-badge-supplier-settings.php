<?php
/**
 * Plugin settings (WooCommerce tab + submenu).
 *
 * @package OSM_Badge_Supplier
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'OSM_Badge_Supplier_Settings', false ) ) {
	return;
}

/**
 * Settings page and options for OSM Badge Supplier.
 */
class OSM_Badge_Supplier_Settings {

	/**
	 * Settings API option group for the submenu form.
	 *
	 * The options.php screen authorises a save with option_page_capability_{group}.
	 * The capability argument of register_setting() is not read there.
	 */
	const SETTINGS_GROUP = 'osm_badge_supplier_settings';

	const OPTION_CLIENT_ID    = 'osm_badge_supplier_client_id';
	const OPTION_LANDING      = 'osm_badge_supplier_landing_slug';
	const OPTION_HOST         = 'osm_badge_supplier_osm_host';
	const OPTION_DRY_RUN      = 'osm_badge_supplier_dry_run';
	const OPTION_RELAY_URL    = 'osm_badge_supplier_relay_url';
	const OPTION_RELAY_SECRET = 'osm_badge_supplier_relay_secret';
	const OPTION_PREV_SLUG    = 'osm_badge_supplier_prev_landing_slug';
	const OPTION_ADMIN_DEBUG  = 'osm_badge_supplier_admin_debug';

	/**
	 * Hook settings into WooCommerce and admin menu.
	 */
	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_osm_badges', array( __CLASS__, 'render_settings_tab' ) );
		add_action( 'woocommerce_update_options_osm_badges', array( __CLASS__, 'save_settings_tab' ) );

		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION_LANDING, array( __CLASS__, 'sanitize_landing_slug' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION_HOST, array( __CLASS__, 'sanitize_host' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION_DRY_RUN, array( __CLASS__, 'sanitize_checkbox' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION_ADMIN_DEBUG, array( __CLASS__, 'sanitize_checkbox' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION_CLIENT_ID, array( __CLASS__, 'sanitize_client_id' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION_RELAY_URL, array( __CLASS__, 'sanitize_relay_url' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION_RELAY_SECRET, array( __CLASS__, 'sanitize_relay_secret' ) );

		add_action( 'admin_menu', array( __CLASS__, 'add_submenu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'update_option_' . self::OPTION_LANDING, array( __CLASS__, 'maybe_flush_on_slug_change' ), 10, 2 );

		// This group only. Other option pages keep manage_options.
		add_filter( 'option_page_capability_' . self::SETTINGS_GROUP, array( __CLASS__, 'option_page_capability' ) );
	}

	/**
	 * Capability options.php should require for one Settings API group.
	 *
	 * The default for every group is manage_options. This plugin lowers that
	 * for its own group so a shop manager can save the submenu. Any other
	 * group is returned unchanged.
	 *
	 * @param string $option_page Option group slug from options.php.
	 * @param string $capability  Capability WordPress would otherwise require.
	 * @return string
	 */
	public static function capability_for_option_page( $option_page, $capability = 'manage_options' ) {
		if ( self::SETTINGS_GROUP === $option_page ) {
			return 'manage_woocommerce';
		}
		return (string) $capability;
	}

	/**
	 * Filter callback for option_page_capability_osm_badge_supplier_settings.
	 *
	 * Hooked only to this plugin's group, so it cannot change another page.
	 *
	 * @param string $capability Incoming capability. Ignored; the hook name is the group.
	 * @return string
	 */
	public static function option_page_capability( $capability ) {
		return self::capability_for_option_page( self::SETTINGS_GROUP, $capability );
	}

	/**
	 * Add WooCommerce settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public static function add_settings_tab( $tabs ) {
		$tabs['osm_badges'] = __( 'OSM Badges', 'osm-badge-supplier' );
		return $tabs;
	}

	/**
	 * Output WooCommerce settings fields for this tab.
	 */
	public static function render_settings_tab() {
		woocommerce_admin_fields( self::get_wc_settings() );
	}

	/**
	 * Save WooCommerce settings tab.
	 */
	public static function save_settings_tab() {
		woocommerce_update_options( self::get_wc_settings() );
	}

	/**
	 * WooCommerce settings field definitions.
	 *
	 * @return array
	 */
	public static function get_wc_settings() {
		return array(
			array(
				'title' => __( 'OSM Badge Supplier', 'osm-badge-supplier' ),
				'type'  => 'title',
				'desc'  => __( 'Connect Online Scout Manager badge orders to this WooCommerce shop.', 'osm-badge-supplier' ),
				'id'    => 'osm_badge_supplier_section',
			),
			array(
				'title'    => __( 'Client ID', 'osm-badge-supplier' ),
				'desc'     => __( 'OSM Badge Supplier client_id. Sent only in the POST body — never in the URL.', 'osm-badge-supplier' ),
				'id'       => self::OPTION_CLIENT_ID,
				'type'     => 'password',
				'css'      => 'min-width:350px;',
				'autoload' => false,
			),
			array(
				'title'    => __( 'Landing path slug', 'osm-badge-supplier' ),
				'desc'     => __( 'URL path for the OSM order landing page (no leading/trailing slashes). Default: osm-order', 'osm-badge-supplier' ),
				'id'       => self::OPTION_LANDING,
				'type'     => 'text',
				'default'  => OSM_BADGE_SUPPLIER_DEFAULT_SLUG,
				'css'      => 'min-width:250px;',
				'autoload' => false,
			),
			array(
				'title'    => __( 'OSM host URL', 'osm-badge-supplier' ),
				'desc'     => __( 'Base URL with no trailing slash. Must be HTTPS. Default: https://www.onlinescoutmanager.co.uk', 'osm-badge-supplier' ),
				'id'       => self::OPTION_HOST,
				'type'     => 'text',
				'default'  => OSM_BADGE_SUPPLIER_DEFAULT_HOST,
				'css'      => 'min-width:400px;',
				'autoload' => false,
			),
			array(
				'title'    => __( 'OSM relay URL (optional)', 'osm-badge-supplier' ),
				'desc'     => __( 'If the shop server IP is blocked by OSM, set a Cloudflare Worker (or similar) HTTPS endpoint that forwards the order POST. Leave empty for direct OSM calls.', 'osm-badge-supplier' ),
				'id'       => self::OPTION_RELAY_URL,
				'type'     => 'text',
				'default'  => '',
				'css'      => 'min-width:400px;',
				'autoload' => false,
			),
			array(
				'title'    => __( 'OSM relay secret', 'osm-badge-supplier' ),
				'desc'     => __( 'Shared secret sent as X-OSM-Relay-Secret. Never shown on the public landing page.', 'osm-badge-supplier' ),
				'id'       => self::OPTION_RELAY_SECRET,
				'type'     => 'password',
				'css'      => 'min-width:350px;',
				'autoload' => false,
			),
			array(
				'title'    => __( 'Dry run', 'osm-badge-supplier' ),
				'desc'     => __( 'Fetch and show mapping but do not add products to the cart.', 'osm-badge-supplier' ),
				'id'       => self::OPTION_DRY_RUN,
				'type'     => 'checkbox',
				'default'  => 'no',
				'autoload' => false,
			),
			array(
				'title'    => __( 'Admin debug panel', 'osm-badge-supplier' ),
				'desc'     => __( 'Show the "Admin debug (OSM HTTP)" panel on the landing page to shop admins. Off by default; never shown to customers.', 'osm-badge-supplier' ),
				'id'       => self::OPTION_ADMIN_DEBUG,
				'type'     => 'checkbox',
				'default'  => 'no',
				'autoload' => false,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'osm_badge_supplier_section',
			),
		);
	}

	/**
	 * Submenu under WooCommerce for quick access.
	 */
	public static function add_submenu() {
		add_submenu_page(
			'woocommerce',
			__( 'OSM Badge Supplier', 'osm-badge-supplier' ),
			__( 'OSM Badge Supplier', 'osm-badge-supplier' ),
			'manage_woocommerce',
			'osm-badge-supplier',
			array( __CLASS__, 'render_submenu_page' )
		);
	}

	/**
	 * Register settings for the submenu form (Settings API).
	 */
	public static function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_CLIENT_ID,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_client_id' ),
				'capability'        => 'manage_woocommerce',
				'default'           => '',
			)
		);
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_LANDING,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_landing_slug' ),
				'capability'        => 'manage_woocommerce',
				'default'           => OSM_BADGE_SUPPLIER_DEFAULT_SLUG,
			)
		);
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_HOST,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_host' ),
				'capability'        => 'manage_woocommerce',
				'default'           => OSM_BADGE_SUPPLIER_DEFAULT_HOST,
			)
		);
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_DRY_RUN,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'capability'        => 'manage_woocommerce',
				'default'           => 'no',
			)
		);
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_ADMIN_DEBUG,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'capability'        => 'manage_woocommerce',
				'default'           => 'no',
			)
		);
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_RELAY_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_relay_url' ),
				'capability'        => 'manage_woocommerce',
				'default'           => '',
			)
		);
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_RELAY_SECRET,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_relay_secret' ),
				'capability'        => 'manage_woocommerce',
				'default'           => '',
			)
		);
	}

	/**
	 * Keep a stored secret when the field was not part of the submission.
	 *
	 * An empty string still clears the value, so an admin can remove it.
	 * Null means the input was omitted (for example another screen in the
	 * same option group) and must not wipe the stored secret.
	 *
	 * @param mixed  $value  Submitted value, or null when absent.
	 * @param string $option Option name to read when the value is absent.
	 * @return string
	 */
	protected static function sanitize_secret_value( $value, $option ) {
		if ( null === $value ) {
			return (string) get_option( $option, '' );
		}
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitize the OSM client ID.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_client_id( $value ) {
		return self::sanitize_secret_value( $value, self::OPTION_CLIENT_ID );
	}

	/**
	 * Sanitize the relay shared secret.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_relay_secret( $value ) {
		return self::sanitize_secret_value( $value, self::OPTION_RELAY_SECRET );
	}

	/**
	 * Sanitize optional relay URL (HTTPS only).
	 *
	 * @param mixed $value Raw value, or null when the field was not submitted.
	 * @return string
	 */
	public static function sanitize_relay_url( $value ) {
		if ( null === $value ) {
			return self::get_relay_url();
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$value = esc_url_raw( $value );
		$value = untrailingslashit( $value );
		if ( '' === $value || 0 !== strpos( $value, 'https://' ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Sanitize landing slug (no slashes, lowercase path-safe).
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_landing_slug( $value ) {
		$value = sanitize_title( (string) $value );
		if ( '' === $value ) {
			$value = OSM_BADGE_SUPPLIER_DEFAULT_SLUG;
		}
		return $value;
	}

	/**
	 * Sanitize OSM host: must be HTTPS, no trailing slash.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_host( $value ) {
		$value = esc_url_raw( trim( (string) $value ) );
		$value = untrailingslashit( $value );
		if ( '' === $value || 0 !== strpos( $value, 'https://' ) ) {
			return OSM_BADGE_SUPPLIER_DEFAULT_HOST;
		}
		if ( 'https://onlinescoutmanager.co.uk' === $value ) {
			return OSM_BADGE_SUPPLIER_DEFAULT_HOST;
		}
		return $value;
	}

	/**
	 * Sanitize checkbox to yes/no.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_checkbox( $value ) {
		return ( 'yes' === $value || '1' === $value || true === $value || 1 === $value ) ? 'yes' : 'no';
	}

	/**
	 * Flush rewrites when landing slug changes.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 */
	public static function maybe_flush_on_slug_change( $old_value, $value ) {
		if ( (string) $old_value !== (string) $value ) {
			update_option( self::OPTION_PREV_SLUG, (string) $old_value, false );
			flush_rewrite_rules();
		}
	}

	/**
	 * Render submenu settings page.
	 */
	public static function render_submenu_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$client_id    = self::get_client_id();
		$slug         = self::get_landing_slug();
		$host         = self::get_osm_host();
		$relay_url    = self::get_relay_url();
		$relay_secret = self::get_relay_secret();
		$dry_run      = self::is_dry_run();
		$debug_on     = self::is_admin_debug();
		$landing      = home_url( '/' . $slug . '/' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'OSM Badge Supplier', 'osm-badge-supplier' ); ?></h1>
			<p><?php echo esc_html__( 'You can also edit these under WooCommerce → Settings → OSM Badges.', 'osm-badge-supplier' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="osm_client_id"><?php echo esc_html__( 'Client ID', 'osm-badge-supplier' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION_CLIENT_ID ); ?>" type="password" id="osm_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text" autocomplete="off" />
							<p class="description"><?php echo esc_html__( 'Sent only in the POST body — never in the URL.', 'osm-badge-supplier' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="osm_landing"><?php echo esc_html__( 'Landing path slug', 'osm-badge-supplier' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION_LANDING ); ?>" type="text" id="osm_landing" value="<?php echo esc_attr( $slug ); ?>" class="regular-text" />
							<p class="description"><?php echo esc_html__( 'Default: osm-order. After changing, visit Settings → Permalinks and Save once.', 'osm-badge-supplier' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="osm_host"><?php echo esc_html__( 'OSM host URL', 'osm-badge-supplier' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION_HOST ); ?>" type="url" id="osm_host" value="<?php echo esc_attr( $host ); ?>" class="regular-text" />
							<p class="description"><?php echo esc_html__( 'HTTPS only, no trailing slash.', 'osm-badge-supplier' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="osm_relay_url"><?php echo esc_html__( 'OSM relay URL (optional)', 'osm-badge-supplier' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION_RELAY_URL ); ?>" type="url" id="osm_relay_url" value="<?php echo esc_attr( $relay_url ); ?>" class="regular-text" />
							<p class="description"><?php echo esc_html__( 'HTTPS endpoint that forwards the order POST when OSM blocks this server. Leave empty for a direct call.', 'osm-badge-supplier' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="osm_relay_secret"><?php echo esc_html__( 'OSM relay secret', 'osm-badge-supplier' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION_RELAY_SECRET ); ?>" type="password" id="osm_relay_secret" value="<?php echo esc_attr( $relay_secret ); ?>" class="regular-text" autocomplete="off" />
							<p class="description"><?php echo esc_html__( 'Shared secret sent as X-OSM-Relay-Secret. Never shown on the public landing page.', 'osm-badge-supplier' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Dry run', 'osm-badge-supplier' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_DRY_RUN ); ?>" value="no" />
							<label>
								<input name="<?php echo esc_attr( self::OPTION_DRY_RUN ); ?>" type="checkbox" value="yes" <?php checked( $dry_run ); ?> />
								<?php echo esc_html__( 'Fetch and show mapping but do not add products to the cart.', 'osm-badge-supplier' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Admin debug panel', 'osm-badge-supplier' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_ADMIN_DEBUG ); ?>" value="no" />
							<label>
								<input name="<?php echo esc_attr( self::OPTION_ADMIN_DEBUG ); ?>" type="checkbox" value="yes" <?php checked( $debug_on ); ?> />
								<?php echo esc_html__( 'Show the "Admin debug (OSM HTTP)" panel on the landing page to shop admins (off by default; never shown to customers).', 'osm-badge-supplier' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php echo esc_html__( 'Order URL for OSM', 'osm-badge-supplier' ); ?></h2>
			<p><?php echo esc_html__( 'Point the OSM Badge Supplier “order URL” to:', 'osm-badge-supplier' ); ?></p>
			<p><code><?php echo esc_html( $landing ); ?></code></p>
			<p class="description"><?php echo esc_html__( 'OSM will append ?order_url={url-encoded relative path}. Test with OSM Launch test order after configuring Client ID and permalinks.', 'osm-badge-supplier' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Stored OSM client ID.
	 *
	 * @return string
	 */
	public static function get_client_id() {
		return (string) get_option( self::OPTION_CLIENT_ID, '' );
	}

	/**
	 * Landing path slug, or the default when the option is empty.
	 *
	 * @return string
	 */
	public static function get_landing_slug() {
		$slug = (string) get_option( self::OPTION_LANDING, OSM_BADGE_SUPPLIER_DEFAULT_SLUG );
		$slug = sanitize_title( $slug );
		return '' !== $slug ? $slug : OSM_BADGE_SUPPLIER_DEFAULT_SLUG;
	}

	/**
	 * OSM host, forced to HTTPS.
	 *
	 * @return string
	 */
	public static function get_osm_host() {
		$host = (string) get_option( self::OPTION_HOST, OSM_BADGE_SUPPLIER_DEFAULT_HOST );
		$host = untrailingslashit( trim( $host ) );
		if ( '' === $host || 0 !== strpos( $host, 'https://' ) ) {
			return OSM_BADGE_SUPPLIER_DEFAULT_HOST;
		}
		return $host;
	}

	/**
	 * Optional HTTPS relay URL, or an empty string.
	 *
	 * @return string
	 */
	public static function get_relay_url() {
		$url = (string) get_option( self::OPTION_RELAY_URL, '' );
		$url = untrailingslashit( trim( $url ) );
		if ( '' === $url || 0 !== strpos( $url, 'https://' ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Shared secret for the optional relay.
	 *
	 * @return string
	 */
	public static function get_relay_secret() {
		return (string) get_option( self::OPTION_RELAY_SECRET, '' );
	}

	/**
	 * Whether orders are mapped without adding them to the cart.
	 *
	 * @return bool
	 */
	public static function is_dry_run() {
		return 'yes' === get_option( self::OPTION_DRY_RUN, 'no' );
	}

	/**
	 * Whether the admin-only OSM HTTP debug panel is enabled (default off).
	 *
	 * @return bool
	 */
	public static function is_admin_debug() {
		return 'yes' === get_option( self::OPTION_ADMIN_DEBUG, 'no' );
	}

	/**
	 * WooCommerce customer id for this session, used to scope nonces.
	 *
	 * Guests share user ID 0, so a nonce action that does not include this
	 * value is the same for every logged-out visitor.
	 *
	 * @return string
	 */
	public static function customer_session_token() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return '';
		}
		if ( method_exists( WC()->session, 'get_customer_unique_id' ) ) {
			return (string) WC()->session->get_customer_unique_id();
		}
		if ( method_exists( WC()->session, 'get_customer_id' ) ) {
			return (string) WC()->session->get_customer_id();
		}
		return '';
	}
}
