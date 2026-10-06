<?php

namespace WP_CLI\I18n\Tests;

use Gettext\Translations;
use WP_CLI\I18n\JsCodeExtractor;
use WP_CLI\Tests\TestCase;

class JsFunctionsScannerTest extends TestCase {

	/**
	 * Helper to extract translations from JavaScript code.
	 *
	 * @param string       $code    JavaScript code.
	 * @param array<mixed> $options Extraction options.
	 * @return Translations
	 */
	private function extract( $code, array $options = [] ) {
		$translations = new Translations();

		JsCodeExtractor::fromString( $code, $translations, $options + [ 'file' => 'test.js' ] );

		return $translations;
	}

	public function test_does_not_extract_anything_without_functions() {
		$translations = $this->extract(
			"__( 'Hello', 'foo-plugin' );",
			[ 'functions' => [] ]
		);

		$this->assertCount( 0, $translations );
	}

	public function test_ignores_comments_if_comment_extraction_is_disabled() {
		$translations = $this->extract(
			"// translators: A comment.\n__( 'Hello', 'foo-plugin' );",
			[ 'extractComments' => false ]
		);

		$translation = $translations->find( null, 'Hello' );
		$this->assertNotFalse( $translation );
		$this->assertSame( [], $translation->getExtractedComments() );
	}

	public function test_extracts_all_comments_with_empty_prefix() {
		$translations = $this->extract(
			"// Any comment.\n__( 'Hello', 'foo-plugin' );",
			[ 'extractComments' => '' ]
		);

		$translation = $translations->find( null, 'Hello' );
		$this->assertNotFalse( $translation );
		$this->assertSame( [ 'Any comment.' ], $translation->getExtractedComments() );
	}

	public function test_extracts_translator_comments_with_prefix() {
		$translations = $this->extract(
			"// translators: A comment.\n__( 'Hello', 'foo-plugin' );",
			[ 'extractComments' => [ 'translators', 'Translators' ] ]
		);

		$translation = $translations->find( null, 'Hello' );
		$this->assertNotFalse( $translation );
		$this->assertSame( [ 'translators: A comment.' ], $translation->getExtractedComments() );
	}
}
