<?php

namespace WP_CLI\I18n;

use eftec\bladeone\BladeOne;

/**
 * Turns Blade templates into PHP code using the BladeOne standalone Blade engine.
 */
class BladeGettextExtractor {

	/**
	 * Prepares a Blade compiler/engine and returns it.
	 *
	 * @param array<string, mixed> $options Options array.
	 * @return BladeOne
	 */
	protected static function getBladeCompiler( array $options = [] ) {

		$cache_path     = ! empty( $options['cachePath'] ) && is_string( $options['cachePath'] ) ? $options['cachePath'] : sys_get_temp_dir();
		$blade_compiler = new BladeOne( null, $cache_path );

		return $blade_compiler;
	}

	/**
	 * Compiles the Blade template string into a PHP string in one step.
	 *
	 * @param string               $text    Blade string to be compiled to a PHP string
	 * @param array<string, mixed> $options Options array.
	 * @return string
	 */
	protected static function compileBladeToPhp( $text, array $options = [] ) {
		return static::getBladeCompiler( $options )->compileString( $text );
	}

	/**
	 * Extracts PHP expressions from Blade component prop bindings.
	 *
	 * BladeOne does not compile <x-component> tags, so bound prop
	 * expressions like :prop="__('text', 'domain')" are left as-is
	 * and invisible to the PHP scanner. This method extracts those
	 * expressions and returns them as PHP code so that any gettext
	 * function calls within them can be detected.
	 *
	 * @param string $text Blade template string.
	 * @return string PHP code containing the extracted expressions.
	 */
	protected static function extractComponentPropExpressions( $text ) {
		$php = '';

		// Match opening (and self-closing) Blade component tags: <x-name ...> or <x-name ... />
		// The attribute region handles quoted strings so that a '>' inside an
		// attribute value does not end the match prematurely.
		if ( ! preg_match_all( '/<x[-:\w]+\s+((?:[^>"\']*(?:"[^"]*"|\'[^\']*\'))*[^>"]*)\/?>/', $text, $tag_matches ) ) {
			return $php;
		}

		foreach ( $tag_matches[1] as $attributes ) {
			// Find :prop="expression" or :prop='expression' bound attributes.
			if ( preg_match_all( '/(?<!\w):[\w.-]+=(["\'])(.*?)\1/s', $attributes, $attr_matches ) ) {
				foreach ( $attr_matches[2] as $expression ) {
					$php .= '<?php ' . $expression . '; ?>';
				}
			}
		}

		return $php;
	}

	/**
	 * Returns the PHP code to scan for a Blade template.
	 *
	 * @param string               $text    Blade template string.
	 * @param array<string, mixed> $options Options array.
	 * @return string
	 */
	protected static function toPhp( $text, array $options = [] ) {
		return static::compileBladeToPhp( $text, $options ) . static::extractComponentPropExpressions( $text );
	}
}
