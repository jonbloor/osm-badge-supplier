<?php
/**
 * Landing page template for OSM badge orders.
 *
 * Expects $view array from OSM_Badge_Supplier_Landing::render().
 *
 * @package OSM_Badge_Supplier
 */

defined( 'ABSPATH' ) || exit;

/** @var array $view */
$view = isset( $view ) && is_array( $view ) ? $view : array();

$error           = isset( $view['error'] ) ? $view['error'] : null;
$error_code      = isset( $view['error_code'] ) ? $view['error_code'] : null;
$section         = isset( $view['section'] ) ? $view['section'] : null;
$added           = isset( $view['added'] ) && is_array( $view['added'] ) ? $view['added'] : array();
$unavailable     = isset( $view['unavailable'] ) && is_array( $view['unavailable'] ) ? $view['unavailable'] : array();
$dry_run         = ! empty( $view['dry_run'] );
$client_id_empty = ! empty( $view['client_id_empty'] );
$cart_url        = isset( $view['cart_url'] ) ? $view['cart_url'] : '#';
$checkout_url    = isset( $view['checkout_url'] ) ? $view['checkout_url'] : '#';
$continue_url    = ! empty( $view['continue_url'] ) ? $view['continue_url'] : home_url( '/' );

$section_label = '';
if ( is_string( $section ) && '' !== $section ) {
	$section_label = $section;
} elseif ( is_array( $section ) ) {
	if ( ! empty( $section['name'] ) ) {
		$section_label = (string) $section['name'];
	} elseif ( ! empty( $section['sectionname'] ) ) {
		$section_label = (string) $section['sectionname'];
	} elseif ( ! empty( $section['groupname'] ) ) {
		$section_label = (string) $section['groupname'];
	}
}
?>
<div class="osm-badge-landing">
	<div class="osm-badge-landing__inner">
		<header class="osm-badge-landing__header">
			<h1 class="osm-badge-landing__title"><?php echo esc_html__( 'OSM Badge Order', 'osm-badge-supplier' ); ?></h1>
			<?php if ( $section_label ) : ?>
				<p class="osm-badge-landing__section">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: section / group name from OSM */
							__( 'Section: %s', 'osm-badge-supplier' ),
							$section_label
						)
					);
					?>
				</p>
			<?php endif; ?>
			<?php if ( $dry_run ) : ?>
				<p class="osm-badge-landing__dry-run"><?php echo esc_html__( 'Dry run is on — products were mapped but not added to the cart.', 'osm-badge-supplier' ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( $client_id_empty && current_user_can( 'manage_woocommerce' ) ) : ?>
			<div class="osm-badge-landing__admin-hint" role="status">
				<p>
					<strong><?php echo esc_html__( 'Admin:', 'osm-badge-supplier' ); ?></strong>
					<?php echo esc_html__( 'OSM client_id is empty. Set it under WooCommerce → Settings → OSM Badges (or WooCommerce → OSM Badge Supplier).', 'osm-badge-supplier' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<?php
		$debug = ( isset( $view['debug'] ) && is_array( $view['debug'] ) ) ? $view['debug'] : null;
		if ( $debug && ! empty( $view['show_admin_debug'] ) && current_user_can( 'manage_woocommerce' ) ) :
			?>
			<div class="osm-badge-landing__debug" role="status">
				<p><strong><?php echo esc_html__( 'Admin debug (OSM HTTP — no secrets):', 'osm-badge-supplier' ); ?></strong></p>
				<ul>
					<li><?php echo esc_html( 'via: ' . ( isset( $debug['via'] ) ? (string) $debug['via'] : '' ) ); ?></li>
					<li><?php echo esc_html( 'http_code: ' . ( isset( $debug['http_code'] ) ? (string) $debug['http_code'] : '' ) ); ?></li>
					<li><?php echo esc_html( 'content_type: ' . ( isset( $debug['content_type'] ) ? (string) $debug['content_type'] : '' ) ); ?></li>
					<li><?php echo esc_html( 'x_blocked: ' . ( isset( $debug['x_blocked'] ) ? (string) $debug['x_blocked'] : '' ) ); ?></li>
					<li><?php echo esc_html( 'has_auth_guid: ' . ( ! empty( $debug['has_auth_guid'] ) ? 'yes' : 'no' ) ); ?></li>
					<li><?php echo esc_html( 'order_url_len: ' . ( isset( $debug['order_url_len'] ) ? (string) $debug['order_url_len'] : '' ) ); ?></li>
					<li><?php echo esc_html( 'body_prefix: ' . ( isset( $debug['body_prefix'] ) ? (string) $debug['body_prefix'] : '' ) ); ?></li>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $error ) : ?>
			<div class="osm-badge-landing__error" role="alert">
				<p><?php echo esc_html( $error ); ?></p>
				<?php if ( in_array( $error_code, array( 'expired', 'osm_status_false', 'missing_order_url', 'invalid_order_url', 'http_error', 'invalid_json', 'http_status', 'osm_blocked' ), true ) ) : ?>
					<p class="osm-badge-landing__hint">
						<?php echo esc_html__( 'Tip: order links from OSM expire after a few minutes. Open the shop again from Online Scout Manager.', 'osm-badge-supplier' ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $unavailable ) ) : ?>
			<section class="osm-badge-landing__unavailable" aria-labelledby="osm-unavailable-heading">
				<h2 id="osm-unavailable-heading"><?php echo esc_html__( 'Unavailable items', 'osm-badge-supplier' ); ?></h2>
				<p><?php echo esc_html__( 'These badges could not be added to your basket. Please note them before checkout — we never guess a product from the badge name.', 'osm-badge-supplier' ); ?></p>
				<ul class="osm-badge-landing__unavailable-list">
					<?php foreach ( $unavailable as $row ) : ?>
						<li>
							<strong><?php echo esc_html( isset( $row['name'] ) ? (string) $row['name'] : '' ); ?></strong>
							<?php if ( ! empty( $row['level'] ) ) : ?>
								<span class="osm-badge-landing__meta">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: badge level */
											__( 'Level: %s', 'osm-badge-supplier' ),
											(string) $row['level']
										)
									);
									?>
								</span>
							<?php endif; ?>
							<?php if ( array_key_exists( 'stock_code', $row ) ) : ?>
								<span class="osm-badge-landing__meta">
									<?php
									$sc = $row['stock_code'];
									echo esc_html(
										sprintf(
											/* translators: %s: stock code or “none” */
											__( 'Stock code: %s', 'osm-badge-supplier' ),
											( null === $sc || '' === (string) $sc ) ? __( '(none)', 'osm-badge-supplier' ) : (string) $sc
										)
									);
									?>
								</span>
							<?php endif; ?>
							<span class="osm-badge-landing__reason"><?php echo esc_html( isset( $row['reason'] ) ? (string) $row['reason'] : '' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $added ) ) : ?>
			<section class="osm-badge-landing__added" aria-labelledby="osm-added-heading">
				<h2 id="osm-added-heading">
					<?php
					echo esc_html(
						$dry_run
							? __( 'Would add to basket', 'osm-badge-supplier' )
							: __( 'Added to basket', 'osm-badge-supplier' )
					);
					?>
				</h2>
				<table class="osm-badge-landing__table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Badge / product', 'osm-badge-supplier' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Stock code / SKU', 'osm-badge-supplier' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Qty', 'osm-badge-supplier' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $added as $row ) : ?>
							<tr>
								<td>
									<?php
									$label = ! empty( $row['product_name'] ) ? (string) $row['product_name'] : (string) ( $row['name'] ?? '' );
									// Level is already a separate staged product; do not append "(n)".
									echo esc_html( $label );
									?>
								</td>
								<td><code><?php echo esc_html( isset( $row['sku'] ) ? (string) $row['sku'] : (string) ( $row['stock_code'] ?? '' ) ); ?></code></td>
								<td><?php echo esc_html( (string) (int) ( $row['quantity'] ?? 0 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>

		<?php if ( ! $error || ! empty( $added ) ) : ?>
			<nav class="osm-badge-landing__actions" aria-label="<?php echo esc_attr__( 'Cart actions', 'osm-badge-supplier' ); ?>">
				<?php if ( ! empty( $added ) && ! $dry_run ) : ?>
					<a class="osm-badge-landing__btn osm-badge-landing__btn--primary" href="<?php echo esc_url( $checkout_url ); ?>">
						<?php echo esc_html__( 'Checkout', 'osm-badge-supplier' ); ?>
					</a>
				<?php endif; ?>
				<a class="osm-badge-landing__btn osm-badge-landing__btn--secondary osm-badge-landing__btn--continue" href="<?php echo esc_url( $continue_url ); ?>">
					<?php echo esc_html__( 'Continue shopping', 'osm-badge-supplier' ); ?>
				</a>
				<?php if ( ! $dry_run ) : ?>
					<?php echo OSM_Badge_Supplier_Empty_Basket::render_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped; admin-only by default. ?>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>
</div>
