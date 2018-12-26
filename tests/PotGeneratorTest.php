<?php

namespace WP_CLI\I18n\Tests;

use Gettext\Translation;
use WP_CLI\I18n\PotGenerator;
use Gettext\Translations;
use PHPUnit_Framework_TestCase;

class PotGeneratorTest extends PHPUnit_Framework_TestCase {
	public function test_adds_correct_amount_of_plural_strings() {
		$translations = new Translations();

		$translation = new Translation( '', '%d cat', '%d cats' );

		$translations[] = $translation;

		$result = PotGenerator::toString( $translations );

		$this->assertContains( 'msgid "%d cat"', $result );
		$this->assertContains( 'msgid_plural "%d cats"', $result );
		$this->assertContains( 'msgstr[0] ""', $result );
		$this->assertContains( 'msgstr[1] ""', $result );
	}
}
