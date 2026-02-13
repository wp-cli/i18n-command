<?php

namespace WP_CLI\I18n;

use eftec\bladeone\BladeOne;

// Modified Gettext Blade extractor that
// uses the up-to-date BladeOne standalone Blade engine,
// correctly supports fromStringMultiple.

/**
 * Class to get gettext strings from blade.php files returning arrays.
 */
class BladeGettextExtractor extends \Gettext\Extractors\PhpCode {

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
			// Find :prop="expression" bound attributes.
			if ( preg_match_all( '/(?<!\w):[\w.-]+="([^"]*)"/', $attributes, $attr_matches ) ) {
				foreach ( $attr_matches[1] as $expression ) {
					$php .= '<?php ' . $expression . '; ?>';
				}
			}
		}

		return $php;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Note: In the parent PhpCode class fromString() uses fromStringMultiple() (overridden here)
	 */
	public static function fromStringMultiple( $text, array $translations, array $options = [] ) {
		$php_string  = static::compileBladeToPhp( $text );
		$php_string .= static::extractComponentPropExpressions( $text );
		return parent::fromStringMultiple( $php_string, $translations, $options );
	}
}
