<?php

namespace WP_CLI\I18n\Tests;

use WP_CLI\I18n\PoGenerator;
use WP_CLI\I18n\PoLoader;
use WP_CLI\I18n\Translation;
use WP_CLI\I18n\Translations;
use WP_CLI\Tests\TestCase;

class PoLoaderTest extends TestCase {

	/**
	 * @param string $po PO file contents.
	 * @return Translations
	 */
	private function load( $po ) {
		$translations = ( new PoLoader() )->loadString( $po );

		$this->assertInstanceOf( Translations::class, $translations );

		return $translations;
	}

	public function test_keeps_strings_and_translations_that_are_zero() {
		$po = <<<'PO'
msgid ""
msgstr ""
"Language: de\n"

msgid "0"
msgstr ""

msgctxt "start of week"
msgid "1"
msgstr "0"

msgid "%d item"
msgid_plural "%d items"
msgstr[0] "0"
msgstr[1] "0"

PO;

		$translations = $this->load( $po );

		$this->assertCount( 3, $translations );
		$this->assertNotNull( $translations->find( null, '0' ) );

		$translation = $translations->find( 'start of week', '1' );
		$this->assertInstanceOf( Translation::class, $translation );
		$this->assertSame( '0', $translation->getTranslation() );

		$result = PoGenerator::toString( $translations );

		$this->assertStringContainsString( "msgid \"0\"\nmsgstr \"\"\n", $result );
		$this->assertStringContainsString( "msgid \"1\"\nmsgstr \"0\"\n", $result );
		$this->assertStringContainsString( "msgstr[0] \"0\"\nmsgstr[1] \"0\"\n", $result );
	}

	public function test_keeps_order_of_headers_flags_and_references() {
		$po = <<<'PO'
msgid ""
msgstr ""
"X-Generator: Poedit\n"
"Language: de\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\n"
"A-Custom-Header: multi "
"line\n"

#: b.php:2 a.php:1
#: b.php:1
#: c.php
#, php-format, fuzzy,js-format
msgid "Hello %s"
msgstr "Hello %s (DE)"

PO;

		$translations = $this->load( $po );

		$this->assertSame(
			[ 'POT-Creation-Date', 'PO-Revision-Date', 'Language', 'X-Generator', 'Plural-Forms', 'A-Custom-Header' ],
			array_slice( array_keys( $translations->getHeaders()->toArray() ), 7 )
		);
		$this->assertSame( 'de', $translations->getLanguage() );
		$this->assertSame( 'multi line', $translations->getHeaders()->get( 'A-Custom-Header' ) );

		$translation = $translations->find( null, 'Hello %s' );
		$this->assertInstanceOf( Translation::class, $translation );

		$this->assertSame( [ 'php-format', 'fuzzy', 'js-format' ], $translation->getFlags()->toArray() );
		$this->assertSame(
			[
				[ 'b.php', 2 ],
				[ 'a.php', 1 ],
				[ 'b.php', 1 ],
				[ 'c.php', null ],
			],
			$translation->getReferenceEntries()
		);

		$result = PoGenerator::toString( $translations );

		$this->assertStringContainsString( "#: b.php:2\n#: a.php:1\n#: b.php:1\n#: c.php\n#, php-format,fuzzy,js-format\n", $result );
	}

	public function test_merges_duplicate_entries() {
		$po = <<<'PO'
#: a.php:1
msgid "Hello"
msgstr ""

#. A comment
#: b.php:2
msgid "Hello"
msgstr "Hello (DE)"

PO;

		$translations = $this->load( $po );

		$this->assertCount( 1, $translations );

		$translation = $translations->find( null, 'Hello' );
		$this->assertInstanceOf( Translation::class, $translation );

		$this->assertSame( 'Hello (DE)', $translation->getTranslation() );
		$this->assertSame( [ 'A comment' ], $translation->getExtractedComments()->toArray() );
		$this->assertCount( 2, $translation->getReferenceEntries() );
	}

	public function test_loads_multiline_and_disabled_entries() {
		$po = <<<'PO'
msgctxt "context"
msgid ""
"First\n"
"second \"quoted\""
msgstr ""
"First (DE)\n"
"second (DE)"

#~ msgid "Obsolete "
#~ "string"
#~ msgstr "Obsolete (DE)"
PO;

		$translations = $this->load( $po );

		$translation = $translations->find( 'context', "First\nsecond \"quoted\"" );
		$this->assertInstanceOf( Translation::class, $translation );
		$this->assertSame( "First (DE)\nsecond (DE)", $translation->getTranslation() );
		$this->assertFalse( $translation->isDisabled() );

		$translation = $translations->find( null, 'Obsolete string' );
		$this->assertInstanceOf( Translation::class, $translation );
		$this->assertSame( 'Obsolete (DE)', $translation->getTranslation() );
		$this->assertTrue( $translation->isDisabled() );

		$result = PoGenerator::toString( $translations );

		$this->assertStringContainsString( "msgctxt \"context\"\nmsgid \"\"\n\"First\\n\"\n\"second \\\"quoted\\\"\"\n", $result );
		$this->assertStringContainsString( "#~ msgid \"Obsolete string\"\n#~ msgstr \"Obsolete (DE)\"\n", $result );
	}

	public function test_loads_multiple_files_into_one_set() {
		$dir = sys_get_temp_dir() . '/' . uniqid( 'wp-cli-i18n-test-', true );
		mkdir( $dir );

		file_put_contents( "$dir/a.pot", "#: a.php:1\nmsgid \"Hello\"\nmsgstr \"\"\n" );
		file_put_contents( "$dir/b.pot", "#: b.php:1\nmsgid \"Hello\"\nmsgstr \"\"\n\nmsgid \"World\"\nmsgstr \"\"\n" );

		$translations = PoLoader::fromFiles( [ "$dir/a.pot", "$dir/b.pot" ] );

		unlink( "$dir/a.pot" );
		unlink( "$dir/b.pot" );
		rmdir( $dir );

		$this->assertCount( 2, $translations );

		$translation = $translations->find( null, 'Hello' );
		$this->assertInstanceOf( Translation::class, $translation );
		$this->assertSame(
			[
				[ 'a.php', 1 ],
				[ 'b.php', 1 ],
			],
			$translation->getReferenceEntries()
		);
	}
}
