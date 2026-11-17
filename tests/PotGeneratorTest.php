<?php

namespace WP_CLI\I18n\Tests;

use WP_CLI\I18n\PotGenerator;
use WP_CLI\I18n\Translations;
use WP_CLI\Tests\TestCase;

class PotGeneratorTest extends TestCase {
	public function test_adds_correct_amount_of_plural_strings() {
		$translations = Translations::create();

		$translations->insert( null, '%d cat', '%d cats' );

		$result = PotGenerator::toString( $translations );

		$this->assertStringContainsString( 'msgid "%d cat"', $result );
		$this->assertStringContainsString( 'msgid_plural "%d cats"', $result );
		$this->assertStringContainsString( 'msgstr[0] ""', $result );
		$this->assertStringContainsString( 'msgstr[1] ""', $result );
	}
}
