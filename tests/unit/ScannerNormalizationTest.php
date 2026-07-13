<?php

use PHPUnit\Framework\TestCase;

final class ScannerNormalizationTest extends TestCase {
	protected function tearDown(): void {
		$GLOBALS['iua_test_get_posts'] = null;
		parent::tearDown();
	}

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

	/**
	 * @dataProvider upload_reference_variants
	 */
	public function test_upload_reference_variants_are_normalized( string $reference ) : void {
		$scanner  = new IUA_Scanner();
		$used     = array();
		$path_map = array( '2024/image.jpg' => 11 );

		$this->call_private( $scanner, 'scan_text_for_uploads', array( $reference, $path_map, &$used, 'fixture' ) );

		$this->assertSame( array( 11 => true ), $used );
	}

	public function upload_reference_variants(): array {
		return array(
			'full URL'          => array( 'https://example.test/wp-content/uploads/2024/image.jpg' ),
			'relative URL'      => array( 'wp-content/uploads/2024/image.jpg' ),
			'srcset'            => array( '<img srcset="/wp-content/uploads/2024/image.jpg 1x, /wp-content/uploads/2024/image-2.jpg 2x">' ),
			'lazy-load field'   => array( '<img data-src="/wp-content/uploads/2024/image.jpg">' ),
			'JSON escaped URL'  => array( '{"url":"https:\\/\\/example.test\\/wp-content\\/uploads\\/2024\\/image.jpg"}' ),
			'HTML escaped URL'  => array( 'https:&#47;&#47;example.test&#47;wp-content&#47;uploads&#47;2024&#47;image.jpg' ),
			'encoded URL'       => array( 'https%3A%2F%2Fexample.test%2Fwp-content%2Fuploads%2F2024%2Fimage.jpg' ),
			'CSS URL'           => array( 'background-image:url(/wp-content/uploads/2024/image.jpg)' ),
			'serialized data'   => array( 'a:1:{s:3:"url";s:46:"/wp-content/uploads/2024/image.jpg";}' ),
			'query and fragment'=> array( '/wp-content/uploads/2024/image.jpg?fit=100#hero' ),
		);
	}

	public function test_blocks_and_shortcodes_only_match_explicit_attachment_fields() : void {
		$scanner = new IUA_Scanner();
		$used    = array();

		$this->call_private(
			$scanner,
			'scan_text_for_attachment_ids',
			array( '<!-- wp:image {"id":27} --><figure></figure> [gallery ids="28, 29"] plain id=30', &$used, 'post:5 content' )
		);

		$this->assertSame( array( 27 => true, 28 => true, 29 => true ), $used );
		$this->assertArrayNotHasKey( 30, $used );
	}

	public function test_close_filenames_and_duplicate_basenames_do_not_false_match() : void {
		$scanner  = new IUA_Scanner();
		$used     = array();
		$path_map = array(
			'2024/image.jpg' => 11,
			'2025/image.jpg' => 12,
		);

		$this->call_private( $scanner, 'scan_text_for_uploads', array( '/wp-content/uploads/2024/image-copy.jpg', $path_map, &$used, 'fixture' ) );
		$this->assertSame( array(), $used );

		$this->call_private( $scanner, 'scan_text_for_uploads', array( '/wp-content/uploads/2025/image.jpg', $path_map, &$used, 'fixture' ) );
		$this->assertSame( array( 12 => true ), $used );
	}

	public function test_attachment_queries_are_batched(): void {
		$calls = array();
		$GLOBALS['iua_test_get_posts'] = static function ( array $args ) use ( &$calls ): array {
			$calls[] = $args;

			return 1 === $args['paged'] ? range( 1, 200 ) : array( 201 );
		};

		$scanner = new IUA_Scanner();
		$ids     = $this->call_private( $scanner, 'get_image_attachment_ids' );

		$this->assertCount( 201, $ids );
		$this->assertCount( 2, $calls );
		$this->assertSame( 200, $calls[0]['posts_per_page'] );
		$this->assertSame( 1, $calls[0]['paged'] );
		$this->assertSame( 2, $calls[1]['paged'] );
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
