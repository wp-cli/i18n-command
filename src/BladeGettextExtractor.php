<?php

namespace WP_CLI\I18n;

use eftec\bladeone\BladeOne;
use Gettext\Translations;

/**
 * Class to get gettext strings from blade.php files returning arrays.
 */
class BladeGettextExtractor extends PhpCodeExtractor {

	/**
	 * Prepares a Blade compiler/engine and returns it.
	 *
	 * @return BladeOne
	 */
	protected static function getBladeCompiler() {
		$cache_path     = empty( $options['cachePath'] ) ? sys_get_temp_dir() : $options['cachePath'];
		$blade_compiler = new BladeOne( null, $cache_path );

		if ( method_exists( $blade_compiler, 'withoutComponentTags' ) ) {
			$blade_compiler->withoutComponentTags();
		}

		return $blade_compiler;
	}

	/**
	 * Compiles the Blade template string into a PHP string in one step.
	 *
	 * @param string $text Blade string to be compiled to a PHP string
	 * @return string
	 */
	protected static function compileBladeToPhp( $text ) {
		return static::getBladeCompiler()->compileString( $text );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function fromString( $text, Translations $translations, array $options = [] ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Using gettext scanner API.
		$php_string = static::compileBladeToPhp( $text );
		return parent::fromString( $php_string, $translations, $options );
	}
}
