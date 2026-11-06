<?php
/**
 * Map OSM badges (stock_code) to WooCommerce products and cart.
 *
 * @package OSM_Badge_Supplier
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'OSM_Badge_Supplier_Cart_Mapper', false ) ) {
	return;
}

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
		$pending     = array();
		$sku_need    = array();
		$seq         = 0;

		foreach ( $badges as $badge ) {
			if ( ! is_array( $badge ) ) {
				continue;
			}

			$name     = isset( $badge['name'] ) ? (string) $badge['name'] : '';
			$level    = isset( $badge['level'] ) ? (string) $badge['level'] : '';
			$type     = isset( $badge['type'] ) ? (string) $badge['type'] : '';
			$quantity = isset( $badge['quantity'] ) ? (int) $badge['quantity'] : 0;
			$stock    = array_key_exists( 'stock_code', $badge ) ? $badge['stock_code'] : null;
			$badge_id = isset( $badge['id'] ) ? (string) $badge['id'] : '';

			$base = array(
				'id'         => $badge_id,
				'name'       => $name,
				'level'      => $level,
				'type'       => $type,
				'quantity'   => $quantity,
				'stock_code' => $stock,
				'_i'         => $seq,
			);
			++$seq;

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
			$candidates = array_values(
				array_unique(
					array_filter(
						array(
							$sku,
							str_replace( ' ', '-', $sku ),
							str_replace( '-', ' ', $sku ),
						)
					)
				)
			);
			foreach ( $candidates as $candidate ) {
				$sku_need[] = $candidate;
			}
			$pending[] = array(
				'base'       => $base,
				'sku'        => $sku,
				'candidates' => $candidates,
			);
		}

		$ids_by_sku = self::product_ids_by_sku( $sku_need );
		$prime_ids  = array();
		foreach ( $pending as $item ) {
			foreach ( $item['candidates'] as $candidate ) {
				if ( ! empty( $ids_by_sku[ $candidate ] ) ) {
					$prime_ids[] = (int) $ids_by_sku[ $candidate ];
					break;
				}
			}
		}
		if ( $prime_ids && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( array_values( array_unique( $prime_ids ) ), false, true );
		}

		foreach ( $pending as $item ) {
			$base       = $item['base'];
			$sku        = $item['sku'];
			$product_id = 0;
			foreach ( $item['candidates'] as $candidate ) {
				if ( ! empty( $ids_by_sku[ $candidate ] ) ) {
					$product_id = (int) $ids_by_sku[ $candidate ];
					$sku        = $candidate;
					break;
				}
			}

			if ( ! $product_id && function_exists( 'wc_get_product_id_by_sku' ) ) {
				foreach ( $item['candidates'] as $candidate ) {
					$found = (int) wc_get_product_id_by_sku( $candidate );
					if ( $found ) {
						$product_id = $found;
						$sku        = $candidate;
						break;
					}
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

				$cart_item_key = WC()->cart->add_to_cart( $product_id, $base['quantity'] );

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

		$by_i = static function ( $a, $b ) {
			return (int) $a['_i'] <=> (int) $b['_i'];
		};
		usort( $unavailable, $by_i );
		usort( $added, $by_i );
		$strip = static function ( $row ) {
			unset( $row['_i'] );
			return $row;
		};

		return array(
			'added'       => array_map( $strip, $added ),
			'unavailable' => array_map( $strip, $unavailable ),
		);
	}

	/**
	 * Resolve many SKUs in one lookup-table query, then fill gaps one by one.
	 *
	 * A group order can contain dozens of badges, and each badge tries up to
	 * three SKU spellings. One IN (...) query replaces that waterfall.
	 * The caller still uses wc_get_product_id_by_sku() for badges this query
	 * does not resolve, so shops without the lookup table keep working.
	 *
	 * @param array $skus SKU strings.
	 * @return array<string,int> SKU => product ID.
	 */
	protected static function product_ids_by_sku( array $skus ) {
		global $wpdb;

		$skus = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $sku ) {
							return trim( (string) $sku );
						},
						$skus
					)
				)
			)
		);
		$found = array();
		if ( ! $skus || ! isset( $wpdb ) ) {
			return $found;
		}

		$table = isset( $wpdb->wc_product_meta_lookup ) ? $wpdb->wc_product_meta_lookup : $wpdb->prefix . 'wc_product_meta_lookup';
		$ready = self::lookup_table_ready( $table );
		if ( $ready ) {
			$placeholders = implode( ',', array_fill( 0, count( $skus ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders are generated to match $skus.
			$sql  = "SELECT sku, product_id FROM {$table} WHERE sku IN ($placeholders)";
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$skus ), ARRAY_A );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					if ( ! isset( $row['sku'] ) || '' === (string) $row['sku'] || isset( $found[ (string) $row['sku'] ] ) ) {
						continue;
					}
					$id = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
					if ( $id ) {
						$found[ (string) $row['sku'] ] = (int) apply_filters( 'woocommerce_get_product_id_by_sku', $id, (string) $row['sku'] );
					}
				}
			}
		}

		return $found;
	}

	/**
	 * Whether the WooCommerce product lookup table exists.
	 *
	 * @param string $table Prefixed table name.
	 * @return bool
	 */
	protected static function lookup_table_ready( $table ) {
		static $cache = array();
		if ( isset( $cache[ $table ] ) ) {
			return $cache[ $table ];
		}
		global $wpdb;
		if ( ! isset( $wpdb ) || '' === $table ) {
			$cache[ $table ] = false;
			return false;
		}
		$like = $wpdb->esc_like( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one existence check per request.
		$have = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		$cache[ $table ] = ( $have === $table );
		return $cache[ $table ];
	}
}
