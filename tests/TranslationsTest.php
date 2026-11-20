<?php

namespace WP_CLI\I18n\Tests;

use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\MoLoader;
use WP_CLI\I18n\Translations;
use WP_CLI\Tests\TestCase;

class TranslationsTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir;

	public function set_up() {
		parent::set_up();

		$this->dir = sys_get_temp_dir() . '/' . uniqid( 'wp-cli-i18n-test-', true );
		mkdir( $this->dir );
	}

	public function tear_down() {
		array_map( 'unlink', (array) glob( $this->dir . '/*' ) );
		rmdir( $this->dir );

		parent::tear_down();
	}

	public function test_loads_strings_and_translations_that_are_zero() {
		file_put_contents(
			"$this->dir/de_DE.po",
			<<<'PO'
msgid ""
msgstr ""
"Language: de\n"

msgid "0"
msgstr ""

msgctxt "start of week"
msgid "1"
msgstr "0"

PO
		);

		$translations = Translations::fromPoFiles( "$this->dir/de_DE.po" );

		$this->assertInstanceOf( Translations::class, $translations );
		$this->assertCount( 2, $translations );
		$this->assertNotNull( $translations->find( null, '0' ) );

		$translation = $translations->find( 'start of week', '1' );
		$this->assertNotNull( $translation );
		$this->assertSame( '0', $translation->getTranslation() );
	}

	public function test_loads_multiple_files_into_one_set() {
		file_put_contents( "$this->dir/a.pot", "#: a.php:1\nmsgid \"Hello\"\nmsgstr \"\"\n" );
		file_put_contents( "$this->dir/b.pot", "#: b.php:1\nmsgid \"Hello\"\nmsgstr \"\"\n\nmsgid \"World\"\nmsgstr \"\"\n" );

		$translations = Translations::fromPoFiles( [ "$this->dir/a.pot", "$this->dir/b.pot" ] );

		$this->assertCount( 2, $translations );

		$translation = $translations->find( null, 'Hello' );
		$this->assertNotNull( $translation );
		$this->assertSame(
			[
				[ 'a.php', 1 ],
				[ 'b.php', 1 ],
			],
			Translations::getReferenceList( $translation )
		);
	}

	public function test_merges_duplicate_entries() {
		$translations = Translations::fromPoString(
			<<<'PO'
msgid ""
msgstr ""
"Language: de\n"

#: a.php:1
msgid "Hello"
msgstr ""

#. A comment
#: b.php:2
msgid "Hello"
msgstr "Hello (DE)"

msgid "World"
msgstr "World (DE)"

PO
		);

		$this->assertCount( 2, $translations );
		$this->assertSame( 'de', $translations->getLanguage() );

		$translation = $translations->find( null, 'Hello' );
		$this->assertNotNull( $translation );
		$this->assertSame( 'Hello (DE)', $translation->getTranslation() );
		$this->assertSame( [ 'A comment' ], $translation->getExtractedComments()->toArray() );
		$this->assertSame(
			[
				[ 'a.php', 1 ],
				[ 'b.php', 2 ],
			],
			Translations::getReferenceList( $translation )
		);
	}

	public function test_keeps_file_comments_and_flags() {
		file_put_contents(
			"$this->dir/de_DE.po",
			<<<'PO'
# Copyright (C) 2026 Foo Plugin
#
#, fuzzy
msgid ""
msgstr ""
"Language: de\n"

#, php-format
msgid "Hello %s"
msgstr "Hello %s (DE)"

PO
		);

		$translations = Translations::fromPoFiles( "$this->dir/de_DE.po" );

		$this->assertSame( 'Copyright (C) 2026 Foo Plugin', $translations->getDescription() );
		$this->assertSame( [ 'fuzzy' ], $translations->getFlags()->toArray() );

		$result = ( new PoGenerator() )->generateString( $translations );

		$this->assertStringStartsWith( "# Copyright (C) 2026 Foo Plugin\n#\n#, fuzzy\nmsgid \"\"\n", $result );
	}

	public function test_insert_returns_existing_translation() {
		$translations = Translations::create();

		$first = $translations->insert( 'context', 'Hello' );
		$first->getReferences()->add( 'a.php', 1 );

		$second = $translations->insert( 'context', 'Hello', 'Hellos' );

		$this->assertSame( $first, $second );
		$this->assertCount( 1, $translations );
		$this->assertSame( 'Hellos', $first->getPlural() );
		$this->assertFalse( $first->isTranslated() );
		$this->assertSame( [ [ 'a.php', 1 ] ], Translations::getReferenceList( $first ) );
	}

	public function test_translations_have_default_headers() {
		$translations = Translations::create( 'foo-plugin' );

		$this->assertSame(
			[
				'Content-Transfer-Encoding',
				'Content-Type',
				'Language',
				'Language-Team',
				'Last-Translator',
				'MIME-Version',
				'PO-Revision-Date',
				'POT-Creation-Date',
				'Project-Id-Version',
				'Report-Msgid-Bugs-To',
				'X-Domain',
			],
			array_keys( $translations->getHeaders()->toArray() )
		);
	}

	public function test_mo_file_includes_headers_and_translations_that_are_zero() {
		$translations = Translations::create();
		$translations->getHeaders()->set( 'Plural-Forms', 'nplurals=2; plural=(n != 1);' );

		$translations->insert( 'start of week', '1' )->translate( '0' );
		$translations->insert( null, 'Untranslated' );
		$translations->insert( null, 'Disabled' )->translate( 'Disabled (DE)' )->disable();
		$translations->insert( null, '%d item', '%d items' )->translate( '%d item (DE)' )->translatePlural( '%d items (DE)' );

		$mo = ( new MoGenerator() )->includeHeaders()->generateString( $translations );

		$this->assertSame( 0x950412de, current( (array) unpack( 'L', $mo ) ) );
		$this->assertSame( 3, current( (array) unpack( 'L', substr( $mo, 8 ) ) ) );
		$this->assertStringContainsString( "Plural-Forms: nplurals=2; plural=(n != 1);\n", $mo );
		$this->assertStringContainsString( "start of week\x041\x00", $mo );
		$this->assertStringContainsString( "%d item\x00%d items\x00", $mo );
		$this->assertStringContainsString( "%d item (DE)\x00%d items (DE)\x00", $mo );

		$loaded = ( new MoLoader() )->loadString( $mo )->find( 'start of week', '1' );
		$this->assertNotNull( $loaded );
		$this->assertSame( '0', $loaded->getTranslation() );

		$this->assertStringNotContainsString( 'Untranslated', $mo );
		$this->assertStringNotContainsString( 'Disabled', $mo );
	}
}
