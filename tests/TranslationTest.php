<?php

namespace WP_CLI\I18n\Tests;

use Gettext\Merge;
use WP_CLI\I18n\MoGenerator;
use WP_CLI\I18n\Translation;
use WP_CLI\I18n\Translations;
use WP_CLI\Tests\TestCase;

class TranslationTest extends TestCase {

	public function test_merge_keeps_translation_that_is_zero() {
		$ours   = Translation::create( null, '1' )->translate( '0' );
		$theirs = Translation::create( null, '1' );

		$this->assertSame( '0', $ours->mergeWith( $theirs )->getTranslation() );
		$this->assertSame( '0', $theirs->mergeWith( $ours )->getTranslation() );

		$theirs->translate( '7' );

		$this->assertSame( '0', $ours->mergeWith( $theirs )->getTranslation() );
		$this->assertSame( '7', $ours->mergeWith( $theirs, Merge::TRANSLATIONS_OVERRIDE )->getTranslation() );
		$this->assertSame( '0', $theirs->mergeWith( $ours, Merge::TRANSLATIONS_OVERRIDE )->getTranslation() );
	}

	public function test_merge_keeps_disabled_state() {
		$ours   = Translation::create( null, 'Hello' )->disable();
		$theirs = Translation::create( null, 'Hello' );

		$this->assertTrue( $ours->mergeWith( $theirs )->isDisabled() );
		$this->assertFalse( $theirs->mergeWith( $ours )->isDisabled() );
	}

	public function test_merge_keeps_order_of_flags_and_references() {
		$ours = Translation::create( null, 'Hello %s' );
		$ours->getFlags()->add( 'php-format' );
		$ours->getReferences()->add( 'b.php', 2 )->add( 'a.php', 1 );

		$theirs = Translation::create( null, 'Hello %s' );
		$theirs->getFlags()->add( 'js-format', 'fuzzy' );
		$theirs->getReferences()->add( 'a.js', 3 )->add( 'b.php', 1 )->add( 'a.php', 1 );

		$merged = $ours->mergeWith( $theirs );

		$this->assertInstanceOf( Translation::class, $merged );
		$this->assertSame( [ 'php-format', 'js-format', 'fuzzy' ], $merged->getFlags()->toArray() );
		$this->assertSame(
			[
				[ 'b.php', 2 ],
				[ 'a.php', 1 ],
				[ 'a.js', 3 ],
				[ 'b.php', 1 ],
			],
			$merged->getReferenceEntries()
		);

		$merged = $ours->mergeWith( $theirs, Merge::REFERENCES_THEIRS | Merge::FLAGS_THEIRS );

		$this->assertInstanceOf( Translation::class, $merged );
		$this->assertSame( [ 'js-format', 'fuzzy' ], $merged->getFlags()->toArray() );
		$this->assertSame(
			[
				[ 'a.js', 3 ],
				[ 'b.php', 1 ],
				[ 'a.php', 1 ],
			],
			$merged->getReferenceEntries()
		);
	}

	public function test_insert_returns_existing_translation() {
		$translations = Translations::create();

		$first = $translations->insert( 'context', 'Hello' );
		$first->getReferences()->add( 'a.php', 1 );

		$second = $translations->insert( 'context', 'Hello', 'Hellos' );

		$this->assertSame( $first, $second );
		$this->assertCount( 1, $translations );
		$this->assertSame( 'Hellos', $first->getPlural() );
		$this->assertTrue( $first->hasPlural() );
		$this->assertTrue( $first->hasContext() );
		$this->assertFalse( $first->hasTranslation() );
	}

	public function test_insert_keeps_data_of_plain_gettext_translation() {
		$translations = Translations::create();

		$plain = \Gettext\Translation::create( null, 'Hello' )->translate( 'Hello (DE)' )->disable();
		$plain->getReferences()->add( 'a.php', 1 );
		$plain->getFlags()->add( 'fuzzy' );
		$translations->add( $plain );

		$translation = $translations->insert( null, 'Hello', 'Hellos' );

		$this->assertCount( 1, $translations );
		$this->assertSame( $translation, $translations->find( null, 'Hello' ) );
		$this->assertSame( 'Hello (DE)', $translation->getTranslation() );
		$this->assertSame( 'Hellos', $translation->getPlural() );
		$this->assertTrue( $translation->isDisabled() );
		$this->assertSame( [ 'fuzzy' ], $translation->getFlags()->toArray() );
		$this->assertSame( [ [ 'a.php', 1 ] ], $translation->getReferenceEntries() );
	}

	public function test_translations_have_default_headers_in_fixed_order() {
		$translations = Translations::create( 'foo-plugin' );
		$translations->getHeaders()->set( 'A-Header', 'value' );

		$this->assertSame(
			[
				'Project-Id-Version',
				'Report-Msgid-Bugs-To',
				'Last-Translator',
				'Language-Team',
				'MIME-Version',
				'Content-Type',
				'Content-Transfer-Encoding',
				'POT-Creation-Date',
				'PO-Revision-Date',
				'Language',
				'X-Domain',
				'A-Header',
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

		$mo = MoGenerator::toString( $translations );

		$this->assertSame( 0x950412de, current( (array) unpack( 'L', $mo ) ) );
		$this->assertSame( 3, current( (array) unpack( 'L', substr( $mo, 8 ) ) ) );
		$this->assertStringContainsString( "Plural-Forms: nplurals=2; plural=(n != 1);\n", $mo );
		$this->assertStringContainsString( "start of week\x041\x00", $mo );
		$this->assertStringContainsString( "%d item\x00%d items\x00", $mo );
		$this->assertStringContainsString( "%d item (DE)\x00%d items (DE)\x00", $mo );
		$this->assertStringNotContainsString( 'Untranslated', $mo );
		$this->assertStringNotContainsString( 'Disabled', $mo );
	}
}
