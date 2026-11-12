<?php
/**
 * Release metadata stays in step.
 *
 * @package OSM_Badge_Supplier
 */

/**
 * Version and text-domain checks.
 */
class VersionTest extends OSM_Test_Case {

	/**
	 * Header, constant, stable tag, and changelog name the same release.
	 */
	public function test_versions_match() {
		$main   = file_get_contents( OSM_BADGE_SUPPLIER_FILE );
		$readme = file_get_contents( OSM_BADGE_SUPPLIER_PATH . 'readme.txt' );

		$this->assertSame( 1, preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main, $header ) );
		$this->assertSame( 1, preg_match( "/define\(\s*'OSM_BADGE_SUPPLIER_VERSION',\s*'([^']+)'\s*\)/", $main, $constant ) );
		$this->assertSame( 1, preg_match( '/^Stable tag:\s*(\S+)/m', $readme, $stable ) );

		$this->assertSame( $header[1], $constant[1] );
		$this->assertSame( $header[1], $stable[1] );
		$this->assertStringContainsString( '= ' . $header[1] . ' =', $readme );
	}

	/**
	 * Every translation call in the plugin names this text domain.
	 */
	public function test_text_domain_on_every_translation_call() {
		$files = array( OSM_BADGE_SUPPLIER_FILE );
		foreach ( array( 'includes', 'templates' ) as $dir ) {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( OSM_BADGE_SUPPLIER_PATH . $dir ) );
			foreach ( $iterator as $file ) {
				if ( $file->isFile() && 'php' === $file->getExtension() ) {
					$files[] = $file->getPathname();
				}
			}
		}

		$pattern = '/(?<![\w])(?:esc_html__|esc_html_e|esc_attr__|esc_attr_e|__|_e|_n|_x|_nx)\s*\(/';
		foreach ( $files as $path ) {
			$code = file_get_contents( $path );
			if ( ! preg_match_all( $pattern, $code, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			foreach ( $matches[0] as $match ) {
				$start = $match[1] + strlen( $match[0] ) - 1;
				$args  = $this->balanced( $code, $start );
				$this->assertStringContainsString(
					"'osm-badge-supplier'",
					$args,
					basename( $path ) . ' has a translation call without the plugin text domain: ' . substr( $args, 0, 120 )
				);
			}
		}
	}

	/**
	 * @param string $code  Source.
	 * @param int    $open  Index of the opening parenthesis.
	 * @return string
	 */
	private function balanced( $code, $open ) {
		$depth  = 0;
		$length = strlen( $code );
		for ( $i = $open; $i < $length; $i++ ) {
			if ( '(' === $code[ $i ] ) {
				++$depth;
			} elseif ( ')' === $code[ $i ] ) {
				--$depth;
				if ( 0 === $depth ) {
					return substr( $code, $open, $i - $open + 1 );
				}
			}
		}
		return substr( $code, $open, 200 );
	}
}
