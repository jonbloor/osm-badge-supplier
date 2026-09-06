<?php
/**
 * Plugin settings (WooCommerce tab + submenu).
 *
 * @package OSM_Badge_Supplier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings page and options for OSM Badge Supplier.
 */
class OSM_Badge_Supplier_Settings {

	const OPTION_CLIENT_ID   = 'osm_badge_supplier_client_id';
	const OPTION_LANDING     = 'osm_badge_supplier_landing_slug';
	const OPTION_HOST        = 'osm_badge_supplier_osm_host';
	const OPTION_DRY_RUN     = 'osm_badge_supplier_dry_run';
	const OPTION_RELAY_URL   = 'osm_badge_supplier_relay_url';
	const OPTION_RELAY_SECRET = 'osm_badge_supplier_relay_secret';
	const OPTION_PREV_SLUG   = 'osm_badge_supplier_prev_landing_slug';

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
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION_CLIENT_ID, 'sanitize_text_field' );

		add_action( 'admin_menu', array( __CLASS__, 'add_submenu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'update_option_' . self::OPTION_LANDING, array( __CLASS__, 'maybe_flush_on_slug_change' ), 10, 2 );
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
			'osm_badge_supplier_settings',
			self::OPTION_CLIENT_ID,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			'osm_badge_supplier_settings',
			self::OPTION_LANDING,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_landing_slug' ),
				'default'           => OSM_BADGE_SUPPLIER_DEFAULT_SLUG,
			)
		);
		register_setting(
			'osm_badge_supplier_settings',
			self::OPTION_HOST,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_host' ),
				'default'           => OSM_BADGE_SUPPLIER_DEFAULT_HOST,
			)
		);
		register_setting(
			'osm_badge_supplier_settings',
			self::OPTION_DRY_RUN,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => 'no',
			)
		);
		register_setting(
			'osm_badge_supplier_settings',
			self::OPTION_RELAY_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_relay_url' ),
				'default'           => '',
			)
		);
		register_setting(
			'osm_badge_supplier_settings',
			self::OPTION_RELAY_SECRET,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
	}

	/**
	 * Sanitize optional relay URL (HTTPS only).
	 *
	 * @param string $value Raw.
	 * @return string
	 */
	public static function sanitize_relay_url( $value ) {
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

		$client_id = self::get_client_id();
		$slug      = self::get_landing_slug();
		$host      = self::get_osm_host();
		$dry_run   = self::is_dry_run();
		$landing   = home_url( '/' . $slug . '/' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'OSM Badge Supplier', 'osm-badge-supplier' ); ?></h1>
			<p><?php echo esc_html__( 'You can also edit these under WooCommerce → Settings → OSM Badges.', 'osm-badge-supplier' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'osm_badge_supplier_settings' ); ?>
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
						<th scope="row"><?php echo esc_html__( 'Dry run', 'osm-badge-supplier' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_DRY_RUN ); ?>" value="no" />
							<label>
								<input name="<?php echo esc_attr( self::OPTION_DRY_RUN ); ?>" type="checkbox" value="yes" <?php checked( $dry_run ); ?> />
								<?php echo esc_html__( 'Fetch and show mapping but do not add products to the cart.', 'osm-badge-supplier' ); ?>
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
	 * @return string
	 */
	public static function get_client_id() {
		return (string) get_option( self::OPTION_CLIENT_ID, '' );
	}

	/**
	 * @return string
	 */
	public static function get_landing_slug() {
		$slug = (string) get_option( self::OPTION_LANDING, OSM_BADGE_SUPPLIER_DEFAULT_SLUG );
		$slug = sanitize_title( $slug );
		return '' !== $slug ? $slug : OSM_BADGE_SUPPLIER_DEFAULT_SLUG;
	}

	/**
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
	 * @return string
	 */
	public static function get_relay_secret() {
		return (string) get_option( self::OPTION_RELAY_SECRET, '' );
	}

	/**
	 * @return bool
	 */
	public static function is_dry_run() {
		return 'yes' === get_option( self::OPTION_DRY_RUN, 'no' );
	}
}
