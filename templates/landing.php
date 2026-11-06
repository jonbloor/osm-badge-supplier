<?php
/**
 * Landing page shell for OSM badge orders.
 *
 * The shop header/footer render immediately. When an order_url is present,
 * the results region shows a spinner until assets/js/landing.js fills it.
 *
 * Expects $view array from OSM_Badge_Supplier_Landing::render().
 *
 * @package OSM_Badge_Supplier
 */

defined( 'ABSPATH' ) || exit;

/** @var array $view */
$view    = isset( $view ) && is_array( $view ) ? $view : array();
$loading = ! empty( $view['loading'] );
$sync_url = ! empty( $view['sync_url'] ) ? $view['sync_url'] : '';
?>
<div class="osm-badge-landing">
	<div class="osm-badge-landing__inner">
		<header class="osm-badge-landing__header">
			<h1 class="osm-badge-landing__title"><?php echo esc_html__( 'OSM Badge Order', 'osm-badge-supplier' ); ?></h1>
		</header>

		<div id="osm-badge-landing-results" class="osm-badge-landing__results" aria-live="polite"<?php echo $loading ? ' data-osm-loading="1"' : ''; ?>>
			<?php if ( $loading ) : ?>
				<div class="osm-badge-landing__loading" role="status" aria-live="polite">
					<div class="osm-badge-landing__spinner" aria-hidden="true"></div>
					<p class="osm-badge-landing__loading-title"><?php echo esc_html__( 'Fetching your badge order…', 'osm-badge-supplier' ); ?></p>
					<p class="osm-badge-landing__loading-hint"><?php echo esc_html__( 'The shop is ready. We are loading badges from Online Scout Manager and updating your basket.', 'osm-badge-supplier' ); ?></p>
					<?php if ( $sync_url ) : ?>
						<noscript>
							<p>
								<a class="osm-badge-landing__btn osm-badge-landing__btn--primary" href="<?php echo esc_url( $sync_url ); ?>">
									<?php echo esc_html__( 'Load badge order', 'osm-badge-supplier' ); ?>
								</a>
							</p>
						</noscript>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<?php include OSM_BADGE_SUPPLIER_PATH . 'templates/landing-results.php'; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
