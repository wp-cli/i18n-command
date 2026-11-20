<?php

namespace WP_CLI\I18n;

use Gettext\Translations;

abstract class Generator {

	/**
	 * Saves the translations in a file.
	 *
	 * @param Translations $translations Translations instance.
	 * @param string       $file         Path of the file to write.
	 * @param array<mixed> $options      Generator options.
	 * @return bool Whether the file was written.
	 */
	public static function toFile( Translations $translations, $file, array $options = [] ) {
		return false !== file_put_contents( $file, static::toString( $translations, $options ) );
	}

	/**
	 * Generates a string with the translations ready to save in a file.
	 *
	 * @param Translations $translations Translations instance.
	 * @param array<mixed> $options      Generator options.
	 * @return string
	 */
	abstract public static function toString( Translations $translations, array $options = [] );
}
