<?php

namespace WP_CLI\I18n\Tests;

use WP_CLI\I18n\PhpCodeExtractor;
use WP_CLI\I18n\PhpFunctionsScanner;
use Gettext\Translation;
use WP_CLI\I18n\Translations;
use WP_CLI\Tests\TestCase;

class PhpFunctionsScannerTest extends TestCase {

	public function test_finds_functions_with_arguments_and_comments() {
		$code = <<<'PHP'
<?php
/* translators: %s: Name */
printf( __( 'Hello %s', 'foo' ), $name );
\_x( 'Con' . 'cat', "context", 'foo' );
__( 'With ' . $variable, 'foo' );
__( 'Nested ' . esc_html__( 'inner', 'foo' ), null );
PHP;

		$scanner = new PhpFunctionsScanner( [ '__', '_x', 'esc_html__' ] );

		$functions = [];
		foreach ( $scanner->scan( $code, 'file.php' ) as $function ) {
			$functions[] = [ $function->getName(), $function->getLine(), $function->getArguments(), $function->getComments() ];
		}

		$this->assertSame(
			[
				[ '__', 3, [ 'Hello %s', 'foo' ], [ 'translators: %s: Name' ] ],
				[ '_x', 4, [ 'Concat', 'context', 'foo' ], [] ],
				[ '__', 5, [ 'With ', 'foo' ], [] ],
				[ '__', 6, [ 'Nested ', null ], [] ],
				[ 'esc_html__', 6, [ 'inner', 'foo' ], [] ],
			],
			$functions
		);
	}

	public function test_recovers_from_syntax_errors() {
		$code = <<<'PHP'
<?php
__( 'Before', 'foo' );
$broken = ;
__( 'After', 'foo' );
PHP;

		$scanner = new PhpFunctionsScanner( [ '__' ] );

		$strings = [];
		foreach ( $scanner->scan( $code, 'file.php' ) as $function ) {
			$strings[] = $function->getArguments()[0];
		}

		$this->assertSame( [ 'Before', 'After' ], $strings );
	}

	public function test_joins_lines_of_multiline_comments() {
		$code = <<<'PHP'
<?php
/*
 * translators: %s: Name
 * of the person.
 */
printf( __( 'Hello %s', 'foo' ), $name );
PHP;

		$translations = Translations::create( 'foo' );

		PhpCodeExtractor::fromString( $code, $translations );

		$translation = $translations->find( null, 'Hello %s' );
		$this->assertInstanceOf( Translation::class, $translation );
		$this->assertSame( [ 'translators: %s: Name of the person.' ], $translation->getExtractedComments()->toArray() );
	}

	public function test_extracts_strings_for_text_domain() {
		$code = <<<'PHP'
<?php
// translators: %d: Number of items.
_n( '%d item', '%d items', $count, 'foo-plugin' );
__( 'Other domain', 'bar-plugin' );
__( '0', 'foo-plugin' );
PHP;

		$translations = Translations::create( 'foo-plugin' );

		PhpCodeExtractor::fromString(
			$code,
			$translations,
			[
				'file'          => 'foo-plugin.php',
				'addReferences' => true,
			]
		);

		$this->assertCount( 2, $translations );
		$this->assertNotNull( $translations->find( null, '0' ) );

		$translation = $translations->find( null, '%d item' );
		$this->assertInstanceOf( Translation::class, $translation );
		$this->assertSame( '%d items', $translation->getPlural() );
		$this->assertSame( [ 'php-format' ], $translation->getFlags()->toArray() );
		$this->assertSame( [ 'translators: %d: Number of items.' ], $translation->getExtractedComments()->toArray() );
		$this->assertSame( [ [ 'foo-plugin.php', 3 ] ], Translations::getReferenceList( $translation ) );
	}
}
