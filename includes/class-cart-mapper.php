<?php
/**
 * Map OSM badges (stock_code) to WooCommerce products and cart.
 *
 * @package OSM_Badge_Supplier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Maps OSM badge lines to WC cart items; never guesses from name.
 */
class OSM_Badge_Supplier_Cart_Mapper {

	/**
	 * Map badges and optionally add to cart.
	 *
	 * @param array $badges  OSM badges array (id, name, level, type, quantity, stock_code).
	 * @param bool  $dry_run If true, map only — do not add to cart.
	 * @return array{
	 *   added: array<int, array>,
	 *   unavailable: array<int, array>
	 * }
	 */
	public static function map_and_add( array $badges, $dry_run = false ) {
		$added       = array();
		$unavailable = array();

		foreach ( $badges as $badge ) {
			if ( ! is_array( $badge ) ) {
				continue;
			}

			$name      = isset( $badge['name'] ) ? (string) $badge['name'] : '';
			$level     = isset( $badge['level'] ) ? (string) $badge['level'] : '';
			$type      = isset( $badge['type'] ) ? (string) $badge['type'] : '';
			$quantity  = isset( $badge['quantity'] ) ? (int) $badge['quantity'] : 0;
			$stock     = array_key_exists( 'stock_code', $badge ) ? $badge['stock_code'] : null;
			$badge_id  = isset( $badge['id'] ) ? (string) $badge['id'] : '';

			$base = array(
				'id'         => $badge_id,
				'name'       => $name,
				'level'      => $level,
				'type'       => $type,
				'quantity'   => $quantity,
				'stock_code' => $stock,
			);

			if ( $quantity < 1 ) {
				$unavailable[] = array_merge(
					$base,
					array(
						'reason' => __( 'Quantity is zero or missing.', 'osm-badge-supplier' ),
					)
				);
				continue;
			}

			// Null / empty stock_code — never guess from name.
			if ( null === $stock || '' === (string) $stock ) {
				$unavailable[] = array_merge(
					$base,
					array(
						'reason' => __( 'No stock code from OSM (cannot map to a shop product).', 'osm-badge-supplier' ),
					)
				);
				continue;
			}

			$sku = trim( (string) $stock );

			if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
				$unavailable[] = array_merge(
					$base,
					array(
						'reason' => __( 'WooCommerce product lookup is unavailable.', 'osm-badge-supplier' ),
					)
				);
				continue;
			}

			/*
			 * OSM stock_code allows letters, numbers and spaces only (max 32).
			 * New Haw catalogue SKUs use hyphens. Try exact match, then
			 * spaces↔hyphens variants so OSM-safe codes still resolve.
			 */
			$product_id = 0;
			$candidates = array_unique(
				array_filter(
					array(
						$sku,
						str_replace( ' ', '-', $sku ),
						str_replace( '-', ' ', $sku ),
					)
				)
			);
			foreach ( $candidates as $candidate ) {
				$found = wc_get_product_id_by_sku( $candidate );
				if ( $found ) {
					$product_id = (int) $found;
					$sku        = $candidate;
					break;
				}
			}
			if ( ! $product_id ) {
				$unavailable[] = array_merge(
					$base,
					array(
						'reason' => sprintf(
							/* translators: %s: SKU / stock_code */
							__( 'No product found with SKU “%s”.', 'osm-badge-supplier' ),
							$sku
						),
					)
				);
				continue;
			}

			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				$unavailable[] = array_merge(
					$base,
					array(
						'reason' => sprintf(
							/* translators: %s: SKU */
							__( 'Product for SKU “%s” could not be loaded.', 'osm-badge-supplier' ),
							$sku
						),
					)
				);
				continue;
			}

			if ( ! $product->is_purchasable() ) {
				$unavailable[] = array_merge(
					$base,
					array(
						'reason' => sprintf(
							/* translators: %s: SKU */
							__( 'Product SKU “%s” is not purchasable.', 'osm-badge-supplier' ),
							$sku
						),
					)
				);
				continue;
			}

			if ( ! $product->is_in_stock() ) {
				$unavailable[] = array_merge(
					$base,
					array(
						'reason' => sprintf(
							/* translators: %s: SKU */
							__( 'Product SKU “%s” is out of stock.', 'osm-badge-supplier' ),
							$sku
						),
					)
				);
				continue;
			}

			$cart_item_key = null;
			$add_error     = '';

			if ( ! $dry_run ) {
				if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
					$unavailable[] = array_merge(
						$base,
						array(
							'reason' => __( 'Shopping cart is not available.', 'osm-badge-supplier' ),
						)
					);
					continue;
				}

				$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );

				if ( ! $cart_item_key ) {
					$unavailable[] = array_merge(
						$base,
						array(
							'reason' => sprintf(
								/* translators: %s: SKU */
								__( 'Could not add SKU “%s” to the cart.', 'osm-badge-supplier' ),
								$sku
							),
						)
					);
					continue;
				}
			}

			$added[] = array_merge(
				$base,
				array(
					'sku'           => $sku,
					'product_id'    => $product_id,
					'product_name'  => $product->get_name(),
					'cart_item_key' => $cart_item_key,
					'dry_run'       => (bool) $dry_run,
				)
			);
		}

		return array(
			'added'       => $added,
			'unavailable' => $unavailable,
		);
	}
}
