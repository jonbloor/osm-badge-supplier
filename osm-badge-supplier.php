<?php
/**
 * Plugin Name:       OSM Badge Supplier
 * Plugin URI:        https://shop.leicestershirescouts.org.uk/
 * Description:       Connect Online Scout Manager badge orders to WooCommerce (map stock_code to SKU, stage cart).
 * Version:           1.0.6
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Leicestershire Scouts Shop Sub Committee
 * Text Domain:       osm-badge-supplier
 * Domain Path:       /languages
 * WC requires at least: 5.0
 * WC tested up to:   11.1
 *
 * @package OSM_Badge_Supplier
 */

defined( 'ABSPATH' ) || exit;

define( 'OSM_BADGE_SUPPLIER_VERSION', '1.0.6' );
define( 'OSM_BADGE_SUPPLIER_FILE', __FILE__ );
define( 'OSM_BADGE_SUPPLIER_PATH', plugin_dir_path( __FILE__ ) );
define( 'OSM_BADGE_SUPPLIER_URL', plugin_dir_url( __FILE__ ) );
define( 'OSM_BADGE_SUPPLIER_DEFAULT_HOST', 'https://www.onlinescoutmanager.co.uk' );
define( 'OSM_BADGE_SUPPLIER_DEFAULT_SLUG', 'osm-order' );

/**
 * Declare WooCommerce feature compatibility (HPOS / custom order tables, etc.).
 *
 * Must run on before_woocommerce_init — before WooCommerce boots features.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			OSM_BADGE_SUPPLIER_FILE,
			true
		);
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks',
			OSM_BADGE_SUPPLIER_FILE,
			true
		);
	}
);

/**
 * Bootstrap after plugins loaded so WooCommerce is available to check.
 */
function osm_badge_supplier_bootstrap() {
	load_plugin_textdomain( 'osm-badge-supplier', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'osm_badge_supplier_wc_missing_notice' );
		return;
	}

	require_once OSM_BADGE_SUPPLIER_PATH . 'includes/class-settings.php';
	require_once OSM_BADGE_SUPPLIER_PATH . 'includes/class-osm-client.php';
	require_once OSM_BADGE_SUPPLIER_PATH . 'includes/class-cart-mapper.php';
	require_once OSM_BADGE_SUPPLIER_PATH . 'includes/class-landing.php';
	require_once OSM_BADGE_SUPPLIER_PATH . 'includes/class-empty-basket.php';

	OSM_Badge_Supplier_Settings::init();
	OSM_Badge_Supplier_Landing::init();
	OSM_Badge_Supplier_Empty_Basket::init();
}
add_action( 'plugins_loaded', 'osm_badge_supplier_bootstrap' );

/**
 * Admin notice when WooCommerce is not active.
 */
function osm_badge_supplier_wc_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'OSM Badge Supplier requires WooCommerce to be installed and active.', 'osm-badge-supplier' );
	echo '</p></div>';
}

/**
 * Activation: flush rewrite rules after registering.
 */
function osm_badge_supplier_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'OSM Badge Supplier requires WooCommerce. Please install and activate WooCommerce first.', 'osm-badge-supplier' ),
			esc_html__( 'Plugin Activation Error', 'osm-badge-supplier' ),
			array( 'back_link' => true )
		);
	}

	require_once OSM_BADGE_SUPPLIER_PATH . 'includes/class-settings.php';
	require_once OSM_BADGE_SUPPLIER_PATH . 'includes/class-landing.php';

	OSM_Badge_Supplier_Landing::register_rewrite();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'osm_badge_supplier_activate' );

/**
 * Deactivation: flush rewrite rules.
 */
function osm_badge_supplier_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'osm_badge_supplier_deactivate' );
