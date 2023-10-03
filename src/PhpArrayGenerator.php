<?php

namespace WP_CLI\I18n;

use Gettext\Generators\PhpArray;
use Gettext\Translations;

/**
 * PHP array file generator.
 *
 * Returns output in the form WordPress uses.
 */
class PhpArrayGenerator extends PhpArray {
	public static $options = [
		'includeHeaders' => false,
	];

	/**
	 * {@inheritdoc}
	 */
	public static function toString( Translations $translations, array $options = [] ) {
		$array = static::generate( $translations, $options );

		$language = $translations->getLanguage();
		if ( null !== $language ) {
			$array['language'] = $language;
		}

		$headers = [
			'X-Generator'  => 'x-generator',
		];

		foreach ( $translations->getHeaders() as $name => $value ) {
			if ( isset( $headers[ $name ] ) ) {
				$array[ $headers[ $name ] ] = $value;
			}
		}

		return '<?php' . PHP_EOL . 'return ' . static::var_export( $array ) . ';';
	}

	/**
	 * Determines if the given array is a list.
	 *
	 * An array is considered a list if its keys consist of consecutive numbers from 0 to count($array)-1.
	 *
	 * Polyfill for array_is_list() in PHP 8.1.
	 *
	 * @see https://github.com/symfony/polyfill-php81/tree/main
	 *
	 * @since 4.0.0
	 *
	 * @codeCoverageIgnore
	 *
	 * @param array<mixed> $arr The array being evaluated.
	 * @return bool True if array is a list, false otherwise.
	 */
	private static function array_is_list( array $arr ) {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $arr );
		}

		if ( ( array() === $arr ) || ( array_values( $arr ) === $arr ) ) {
			return true;
		}

		$next_key = -1;

		foreach ( $arr as $k => $v ) {
			if ( ++$next_key !== $k ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Outputs or returns a parsable string representation of a variable.
	 *
	 * Like {@see var_export()} but "minified", using short array syntax
	 * and no newlines.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $value The variable you want to export.
	 * @return string The variable representation.
	 */
	private static function var_export( $value ) {
		if ( ! is_array( $value ) ) {
			return var_export( $value, true );
		}

		$entries = array();

		$is_list = self::array_is_list( $value );

		foreach ( $value as $key => $val ) {
			$entries[] = $is_list ? self::var_export( $val ) : var_export( $key, true ) . '=>' . self::var_export( $val );
		}

		return '[' . implode( ',', $entries ) . ']';
	}
}
