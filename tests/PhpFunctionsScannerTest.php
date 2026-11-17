<?php

namespace WP_CLI\I18n\Tests;

use WP_CLI\I18n\PhpCodeExtractor;
use WP_CLI\I18n\PhpFunctionsScanner;
use WP_CLI\I18n\Translation;
use WP_CLI\I18n\Translations;
use WP_CLI\Tests\TestCase;

class PhpFunctionsScannerTest extends TestCase {

	/**
	 * @dataProvider data_convert_string
	 *
	 * @param string $literal  String literal as found in PHP code.
	 * @param string $expected Expected value.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'data_convert_string' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function test_convert_string( $literal, $expected ) {
		$this->assertSame( $expected, PhpFunctionsScanner::convertString( $literal ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_convert_string() {
		return [
			'single quoted'             => [ "'Hello'", 'Hello' ],
			'double quoted'             => [ '"Hello"', 'Hello' ],
			'escaped single quote'      => [ "'It\\'s'", "It's" ],
			'single quoted backslashes' => [ "'a\\\\b\\nc'", 'a\\b\\nc' ],
			'double quoted escapes'     => [ '"a\\nb\\tc\\\\d\\"e\\$f"', "a\nb\tc\\d\"e\$f" ],
			'hexadecimal and octal'     => [ '"\\x41\\101"', 'AA' ],
			'unicode code point'        => [ '"\\u{e9}\\u{1f600}"', "\u{e9}\u{1f600}" ],
			'unknown escape is kept'    => [ '"100\\%"', '100\\%' ],
		];
	}

	public function test_finds_functions_with_arguments_and_comments() {
		$code = <<<'PHP'
<?php
/* translators: %s: Name */
printf( __( 'Hello %s', 'foo' ), $name );
\_x( 'Con' . 'cat', "context", 'foo' );
__( 'With ' . $variable, 'foo' );
__( 'Nested ' . esc_html__( 'inner', 'foo' ), null );
PHP;

		$scanner = new PhpFunctionsScanner( $code );
		$scanner->enableCommentsExtraction( [ 'translators' ] );

		$functions = [];
		foreach ( $scanner->getFunctions() as $function ) {
			$comments = [];
			foreach ( $function[3] ?? [] as $comment ) {
				$comments[] = $comment->getComment();
			}

			$functions[] = [ $function[0], $function[1], $function[2], $comments ];
		}

		$this->assertSame(
			[
				[ '__', 3, [ 'Hello %s', 'foo' ], [ 'translators: %s: Name' ] ],
				[ 'printf', 3, [ null, null ], [ 'translators: %s: Name' ] ],
				[ '_x', 4, [ 'Concat', 'context', 'foo' ], [] ],
				[ '__', 5, [ 'With ', 'foo' ], [] ],
				[ 'esc_html__', 6, [ 'inner', 'foo' ], [] ],
				[ '__', 6, [ 'Nested ', null ], [] ],
			],
			$functions
		);
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
		$this->assertSame( [ [ 'foo-plugin.php', 3 ] ], $translation->getReferenceEntries() );
	}
}
