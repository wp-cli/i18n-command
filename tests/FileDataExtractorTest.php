<?php

namespace WP_CLI\I18n\Tests;

use WP_CLI\I18n\FileDataExtractor;
use WP_CLI\Tests\TestCase;

class FileDataExtractorTest extends TestCase {
	public function test_extracts_headers_without_line_numbers() {
		$text = <<<'TEXT'
<?php
/**
 * Plugin Name: My Plugin
 * Description: A test plugin
 * Version: 1.0.0
 */
TEXT;

		$headers = FileDataExtractor::get_file_data_from_string(
			$text,
			[
				'Plugin Name' => 'Plugin Name',
				'Description' => 'Description',
				'Version'     => 'Version',
			]
		);

		$this->assertEquals( 'My Plugin', $headers['Plugin Name'] );
		$this->assertEquals( 'A test plugin', $headers['Description'] );
		$this->assertEquals( '1.0.0', $headers['Version'] );
	}

	public function test_extracts_headers_with_line_numbers() {
		$text = <<<'TEXT'
<?php
/**
 * Plugin Name: My Plugin
 * Description: A test plugin
 * Version: 1.0.0
 */
TEXT;

		$headers = FileDataExtractor::get_file_data_from_string(
			$text,
			[
				'Plugin Name' => 'Plugin Name',
				'Description' => 'Description',
				'Version'     => 'Version',
			],
			true
		);

		$this->assertIsArray( $headers['Plugin Name'] );
		$this->assertEquals( 'My Plugin', $headers['Plugin Name']['value'] );
		$this->assertEquals( 3, $headers['Plugin Name']['line'] );

		$this->assertIsArray( $headers['Description'] );
		$this->assertEquals( 'A test plugin', $headers['Description']['value'] );
		$this->assertEquals( 4, $headers['Description']['line'] );

		$this->assertIsArray( $headers['Version'] );
		$this->assertEquals( '1.0.0', $headers['Version']['value'] );
		$this->assertEquals( 5, $headers['Version']['line'] );
	}

	public function test_line_numbers_with_different_line_endings() {
		// Test with different line positions
		$text = "<?php\n\n\n/**\n * Plugin Name: Test Plugin\n * Description: Description here\n */";

		$headers = FileDataExtractor::get_file_data_from_string(
			$text,
			[
				'Plugin Name' => 'Plugin Name',
				'Description' => 'Description',
			],
			true
		);

		$this->assertEquals( 'Test Plugin', $headers['Plugin Name']['value'] );
		$this->assertEquals( 5, $headers['Plugin Name']['line'] );

		$this->assertEquals( 'Description here', $headers['Description']['value'] );
		$this->assertEquals( 6, $headers['Description']['line'] );
	}

	public function test_empty_headers_with_line_numbers() {
		$text = '<?php // No headers here';

		$headers = FileDataExtractor::get_file_data_from_string(
			$text,
			[
				'Plugin Name' => 'Plugin Name',
			],
			true
		);

		$this->assertIsArray( $headers['Plugin Name'] );
		$this->assertEquals( '', $headers['Plugin Name']['value'] );
		$this->assertEquals( 0, $headers['Plugin Name']['line'] );
	}

	public function test_theme_headers_with_line_numbers() {
		$text = <<<'TEXT'
/*
Theme Name: My Theme
Description: A beautiful theme
Author: John Doe
Version: 2.0.0
*/
TEXT;

		$headers = FileDataExtractor::get_file_data_from_string(
			$text,
			[
				'Theme Name'  => 'Theme Name',
				'Description' => 'Description',
				'Author'      => 'Author',
				'Version'     => 'Version',
			],
			true
		);

		$this->assertEquals( 'My Theme', $headers['Theme Name']['value'] );
		$this->assertEquals( 2, $headers['Theme Name']['line'] );

		$this->assertEquals( 'A beautiful theme', $headers['Description']['value'] );
		$this->assertEquals( 3, $headers['Description']['line'] );

		$this->assertEquals( 'John Doe', $headers['Author']['value'] );
		$this->assertEquals( 4, $headers['Author']['line'] );

		$this->assertEquals( '2.0.0', $headers['Version']['value'] );
		$this->assertEquals( 5, $headers['Version']['line'] );
	}

	public function test_header_with_trailing_comment() {
		$text = <<<'TEXT'
<?php
/**
 * Plugin Name: My Plugin */
 */
TEXT;

		$headers = FileDataExtractor::get_file_data_from_string(
			$text,
			[
				'Plugin Name' => 'Plugin Name',
			],
			true
		);

		$this->assertEquals( 'My Plugin', $headers['Plugin Name']['value'] );
		$this->assertEquals( 3, $headers['Plugin Name']['line'] );
	}
}
