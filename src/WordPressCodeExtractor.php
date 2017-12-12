<?php

namespace WP_CLI\Makepot;

use Gettext\Extractors\PhpCode;
use Gettext\Translations;

class WordPressCodeExtractor extends PhpCode {
	protected static $dir = '';

	public static $options = [
		'extractComments' => 'translators',
		'constants'       => [],
		'functions'       => [
			'_'                    => 'gettext',
			'__'                   => 'gettext',
			'_e'                   => 'gettext',
			'_c'                   => 'pgettext',
			'_n'                   => 'ngettext',
			'_n_noop'              => 'noop',
			'_nc'                  => 'gettext',
			'__ngettext'           => 'gettext',
			'__ngettext_noop'      => 'noop',
			'_x'                   => 'pgettext',
			'_ex'                  => 'pgettext',
			'_nx'                  => 'npgettext',
			'_nx_noop'             => 'noop',
			'_n_js'                => 'ngettext',
			'_nx_js'               => 'npgettext',
			'esc_attr__'           => 'gettext',
			'esc_html__'           => 'gettext',
			'esc_attr_e'           => 'gettext',
			'esc_html_e'           => 'gettext',
			'esc_attr_x'           => 'gettext',
			'esc_html_x'           => 'pgettext',
			'comments_number_link' => 'ngettext',
		],
	];

	/**
	 * {@inheritdoc}
	 */
	public static function fromString( $string, Translations $translations, array $options = [] ) {
		// Make sure a relative file path is added as a comment.
		$options['file'] = ltrim( str_replace( static::$dir, '', $options['file'] ), '/' );

		/*
		 * Todo: Make own implementation using custom PhpFunctionsScanner.
		 *
		 * The default gettext functions have the context first, but WordPress doesn't.
		 * That's why this currently causes wrong POT files to be generated.
		 */
		parent::fromString( $string, $translations, $options );
	}

	/**
	 * Recursively extracts the translations from a directory.
	 *
	 * @param string $dir A path of a directory.
	 * @param Translations $translations The translations instance to append the new translations.
	 * @param array $options
	 */
	public static function fromDirectory( $dir, Translations $translations, array $options = [] ) {
		static::$dir = $dir;

		$files = static::getFilesFromDirectory( $dir );

		static::fromFile( $files, $translations, $options );

		static::$dir = '';
	}

	/**
	 * Recursively gets all PHP files within a directory.
	 *
	 * @param string $dir A path of a directory.
	 *
	 * @return array File list.
	 */
	protected static function getFilesFromDirectory( $dir ) {
		$files = [];

		$old_cwd = getcwd();

		chdir( $dir );

		$file_names = (array) scandir( '.', SCANDIR_SORT_NONE );

		foreach ( $file_names as $file_name ) {
			if ( '.' === $file_name || '..' === $file_name ) {
				continue;
			}

			if ( preg_match( '/\.php$/', $file_name ) ) {
				$files[] = "$dir/$file_name";
				continue;
			}

			if ( is_dir( $file_name ) ) {
				$files += self::getFilesFromDirectory( "$dir/$file_name" );
			}
		}

		chdir( $old_cwd );

		return $files;
	}
}
