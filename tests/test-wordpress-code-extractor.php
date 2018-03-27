<?php

namespace WP_CLI\Makepot;

use WP_CLI\Makepot\WordPress_Code_Extractor;
use Gettext\Translations;
use WP_CLI_Command;

class WordPress_Code_Extractor_Test extends \PHPUnit_Framework_TestCase
{
	public function test_fromString() {
		$file1 = dirname( __FILE__ ) . '/data/sample-1.php';
		$file2 = dirname( __FILE__ ) . '/data/sample-1.php';

		$translations = Translations::fromPhpCodeFile( $file1 );

		WordPress_Code_Extractor::fromFile( $file2, $translations, array(
			'wpExtractTemplates' => 'Foo Plugin 2'
		) );

		$functions = new WordPress_Functions_Scanner( $string );
		var_dump( $translations );
	}
}
