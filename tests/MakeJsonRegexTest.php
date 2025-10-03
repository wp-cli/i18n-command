<?php

namespace WP_CLI\I18n\Tests;

use WP_CLI\I18n\MakeJsonCommand;
use WP_CLI\Tests\TestCase;

/**
 * Test the make_json function's regex functionality for .min. file handling.
 *
 * This test specifically focuses on testing that the make_json function correctly
 * strips .min. from filenames but not incorrectly match other patterns like
 * testadmin.js (which would be a bug if dots were not properly escaped).
 */
class MakeJsonRegexTest extends TestCase {
	/** @var string A path files are located */
	private static $base;
	/** @var \ReflectionMethod make_json reflection (protected) */
	private static $make_json = null;
	/** @var MakeJsonCommand instance */
	private static $obj = null;

	public function set_up() {
		parent::set_up();
		self::$base      = __DIR__ . '/data/';
		self::$obj       = new MakeJsonCommand();
		$reflection      = new \ReflectionClass( get_class( self::$obj ) );
		self::$make_json = $reflection->getMethod( 'make_json' );
		if ( PHP_VERSION_ID < 80100 ) {
			self::$make_json->setAccessible( true );
		}
	}

	/**
	 * Test that make_json function correctly handles minified file names.
	 *
	 * This test creates PO files with various JS file references and verifies that:
	 * 1. Files with .min. are correctly stripped (e.g., script.min.js -> script.js)
	 * 2. Files without .min. are left unchanged (e.g., testadmin.js stays testadmin.js)
	 * 3. Edge cases are handled properly
	 */
	public function test_make_json_strips_min_correctly() {
		// Create a temporary PO file content with various JS references
		$po_content = <<<PO
msgid ""
msgstr ""
"Project-Id-Version: Test Plugin\n"
"Language: de_DE\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"PO-Revision-Date: 2023-10-01T10:00:00+00:00\n"
"X-Domain: test-plugin\n"

#: script.min.js:10
msgid "Script Text"
msgstr "Script Text"

#: admin.min.js:15
msgid "Admin Text"
msgstr "Admin Text"

#: testadmin.js:20
msgid "Test Admin Text"
msgstr "Test Admin Text"

#: superadmin.js:25
msgid "Super Admin Text"
msgstr "Super Admin Text"

#: lib.min.min.js:30
msgid "Lib Text"
msgstr "Lib Text"

#: something.admin.js:35
msgid "Something Admin Text"
msgstr "Something Admin Text"
PO;

		$temp_po_file = tempnam( sys_get_temp_dir(), 'test-po-' ) . '.po';
		$temp_dir     = sys_get_temp_dir();

		try {
			file_put_contents( $temp_po_file, $po_content );

			// Call the make_json function
			$result = self::$make_json->invoke(
				self::$obj,
				$temp_po_file,
				$temp_dir,
				null,
				'',
				[ 'js' ]
			);

			// Verify that JSON files were created
			$this->assertIsArray( $result );
			$this->assertNotEmpty( $result );

			// Check the sources in the generated JSON files
			$found_sources = [];
			foreach ( $result as $json_file ) {
				$this->assertFileExists( $json_file );
				$json_content = json_decode( file_get_contents( $json_file ), true );

				// The JSON structure should contain the source information
				// We need to examine the actual structure of the generated JSON
				if ( isset( $json_content['source'] ) ) {
					$found_sources[] = $json_content['source'];
				}
			}

			$expected_sources = [
				'script.js',
				'admin.js',
				'testadmin.js',
				'superadmin.js',
				'lib.min.js',
				'something.admin.js',
			];

			// Check that we have the expected number of sources
			$this->assertCount(
				count( $expected_sources ),
				$found_sources,
				'Expected ' . count( $expected_sources ) . ' sources but found ' . count( $found_sources )
			);

			// Check that all expected sources are present
			foreach ( $expected_sources as $expected_source ) {
				$this->assertContains(
					$expected_source,
					$found_sources,
					"Expected source '{$expected_source}' not found in results: " . implode( ', ', $found_sources )
				);
			}

			// Specifically verify that problematic files were NOT incorrectly modified
			$this->assertContains( 'testadmin.js', $found_sources, 'testadmin.js should remain unchanged' );
			$this->assertContains( 'superadmin.js', $found_sources, 'superadmin.js should remain unchanged' );
			$this->assertContains( 'something.admin.js', $found_sources, 'something.admin.js should remain unchanged' );

		} finally {
			// Clean up
			if ( file_exists( $temp_po_file ) ) {
				unlink( $temp_po_file );
			}

			// Clean up any generated JSON files
			foreach ( glob( $temp_dir . '/test-plugin-*-*.json' ) as $file ) {
				unlink( $file );
			}
		}
	}
}
