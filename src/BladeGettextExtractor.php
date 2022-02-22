<?php

namespace WP_CLI\I18n;

use Gettext\Translations;
use eftec\bladeone\BladeOne;

// Modified Gettext Blade extractor that uses the up-to-date BladeOne standalone Blade engine

/**
 * Class to get gettext strings from blade.php files returning arrays.
 */
class BladeGettextExtractor extends \Gettext\Extractors\Extractor implements \Gettext\Extractors\ExtractorInterface {
	/**
	 * {@inheritdoc}
	 */
	public static function fromString( $string, Translations $translations, array $options = [] ) {
		if ( empty( $options['facade'] ) ) {
			$cache_path     = empty( $options['cachePath'] ) ? sys_get_temp_dir() : $options['cachePath'];
			$blade_compiler = new BladeOne( null, $cache_path );

			if ( method_exists( $blade_compiler, 'withoutComponentTags' ) ) {
				$blade_compiler->withoutComponentTags();
			}

			$string = $blade_compiler->compileString( $string );
		} else {
			$string = $options['facade']::compileString( $string );
		}

		\Gettext\Extractors\PhpCode::fromString( $string, $translations, $options );
	}
}
