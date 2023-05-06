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

		$headers = [
			'Plural-Forms' => 'plural-forms',
			'X-Generator'  => 'generator',
			'X-Domain'     => 'domain',
			'Language'     => 'language',
		];

		foreach ( $translations->getHeaders() as $name => $value ) {
			if ( ! isset( $headers[ $name ] ) ) {
				continue;
			}

			$array[ $headers[ $name] ] = $value;
		}

		return '<?php' . PHP_EOL . 'return ' . static::var_export( $array, true ) . ';';
	}

	/**
	 * Outputs or returns a parsable string representation of a variable.
	 *
	 * Like {@see var_export()} but "minified", using short array syntax
	 * and no newlines.
	 *
	 * @param mixed $value       The variable you want to export.
	 * @param bool  $return_only Optional. Whether to return the variable representation instead of outputing it. Default false.
	 * @return string|void The variable representation or void.
	 */
	public static function var_export( $value, $return_only = false ) {
		if ( is_array( $value ) ) {
			$entries = array();
			foreach ( $value as $key => $val ) {
				$entries[] = var_export( $key, true ) . '=>' . static::var_export( $val, true );
			}

			$code = '[' . implode( ',', $entries ) . ']';
			if ( $return_only ) {
				return $code;
			}

			echo $code;
		} else {
			return var_export( $value, $return_only );
		}
	}
}
