<?php

use PHPUnit\Framework\TestCase;

final class ScannerNormalizationTest extends TestCase {
	/**
	 * @return mixed
	 */
	private function call_private( IUA_Scanner $scanner, string $method, array $arguments = array() ) {
		$reflection = new ReflectionMethod( IUA_Scanner::class, $method );

		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invokeArgs( $scanner, $arguments );
	}

	public function test_cdn_aliases_and_rewrites_normalize_upload_urls() : void {
		$GLOBALS['iua_test_options'] = array(
			'iua_cdn_aliases'  => 'cdn.example.test, media.example.test',
			'iua_cdn_rewrites' => 'https://assets.example.test/media => /wp-content/uploads',
		);
		$scanner = new IUA_Scanner();

		$this->call_private( $scanner, 'load_cdn_settings' );

		$this->assertSame(
			'/wp-content/uploads/2024/image.jpg',
			$this->call_private( $scanner, 'normalize_text_rewrites', array( 'https://cdn.example.test/wp-content/uploads/2024/image.jpg' ) )
		);
		$this->assertSame(
			'/wp-content/uploads/2024/image.jpg',
			$this->call_private( $scanner, 'normalize_text_rewrites', array( 'https://assets.example.test/media/2024/image.jpg' ) )
		);
	}

	public function test_upload_urls_match_generated_sizes_and_record_provenance() : void {
		$scanner  = new IUA_Scanner();
		$used     = array();
		$path_map = array(
			'2024/image.jpg'         => 11,
			'2024/image-300x200.jpg' => 11,
		);

		$this->call_private(
			$scanner,
			'scan_text_for_uploads',
			array( '<img src="/wp-content/uploads/2024/image-300x200.jpg?cache=1">', $path_map, &$used, 'post:42 content:url' )
		);

		$this->assertSame( array( 11 => true ), $used );
		$this->assertSame(
			array( 11 => array( 'post:42 content:url' ) ),
			$this->call_private( $scanner, 'get_provenance_output' )
		);
	}

	public function test_builder_ids_are_detected_and_provenance_is_capped() : void {
		$scanner = new IUA_Scanner();
		$used    = array();

		$this->call_private( $scanner, 'scan_builder_value_for_ids', array( '{"id":27,"nested":{"id":28}}', &$used, 'post:8 meta:_elementor_data' ) );
		$this->assertSame( array( 27 => true, 28 => true ), $used );

		for ( $index = 0; $index < 14; $index++ ) {
			$this->call_private( $scanner, 'add_provenance', array( 27, 'source:' . $index ) );
		}

		$provenance = $this->call_private( $scanner, 'get_provenance_output' );
		$this->assertCount( 12, $provenance[27] );
		$this->assertSame( 'post:8 meta:_elementor_data json:id', $provenance[27][0] );
	}

	/**
	 * @dataProvider dangerous_csv_values
	 */
	public function test_csv_formula_values_are_neutralized( string $value ) : void {
		$this->assertSame( "'" . $value, IUA_CSV::neutralize_formula( $value ) );
	}

	public function dangerous_csv_values() : array {
		return array(
			'equals'             => array( '=1+1' ),
			'plus'               => array( '+SUM(1,2)' ),
			'minus'              => array( '-1+2' ),
			'at'                 => array( '@SUM(1,2)' ),
			'leading whitespace' => array( " \t=1+1" ),
			'tab'                => array( "\tplain" ),
			'carriage return'    => array( "\rplain" ),
		);
	}

	public function test_safe_csv_values_are_unchanged() : void {
		$this->assertSame( 'image.jpg', IUA_CSV::neutralize_formula( 'image.jpg' ) );
		$this->assertSame( 'https://example.test/image.jpg', IUA_CSV::neutralize_formula( 'https://example.test/image.jpg' ) );
	}

	public function test_cdn_settings_accept_bounded_hosts_and_upload_rewrites() : void {
		$result = IUA_CDN_Settings::validate(
			'CDN.Example.test, 192.0.2.10',
			"https://assets.example.test/media => /wp-content/uploads\n/media => /wp-content/uploads"
		);

		$this->assertTrue( $result['valid'] );
		$this->assertSame( 'cdn.example.test, 192.0.2.10', $result['aliases'] );
		$this->assertSame( "https://assets.example.test/media => /wp-content/uploads\n/media => /wp-content/uploads", $result['rewrites'] );
	}

	/**
	 * @dataProvider invalid_cdn_settings
	 */
	public function test_cdn_settings_reject_malformed_or_dangerous_values( string $aliases, string $rewrites ) : void {
		$result = IUA_CDN_Settings::validate( $aliases, $rewrites );

		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	public function invalid_cdn_settings() : array {
		return array(
			'alias with scheme'       => array( 'https://cdn.example.test', '' ),
			'alias with path'         => array( 'cdn.example.test/path', '' ),
			'missing separator'       => array( '', 'https://cdn.example.test/media' ),
			'non-http source'         => array( '', 'javascript:alert(1) => /wp-content/uploads' ),
			'unrecognized target'     => array( '', '/media => /tmp' ),
			'overly broad source'     => array( '', '/ => /wp-content/uploads' ),
			'too many aliases'        => array( implode( ',', array_fill( 0, 21, 'cdn.example.test' ) ), '' ),
			'too many rewrite rules'  => array( '', implode( "\n", array_fill( 0, 21, '/media => /wp-content/uploads' ) ) ),
		);
	}
}
