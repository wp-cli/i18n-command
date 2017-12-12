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
			'_'                    => 'dgettext',
			'__'                   => 'dgettext',
			'_e'                   => 'dgettext',
			'_c'                   => 'pgettext',
			'_n'                   => 'ngettext',
			'_n_noop'              => 'noop',
			'_nc'                  => 'dgettext',
			'__ngettext'           => 'dgettext',
			'__ngettext_noop'      => 'noop',
			'_x'                   => 'pgettext',
			'_ex'                  => 'pgettext',
			'_nx'                  => 'dnpgettext',
			'_nx_noop'             => 'noop',
			'_n_js'                => 'ngettext',
			'_nx_js'               => 'npgettext',
			'esc_attr__'           => 'dgettext',
			'esc_html__'           => 'dgettext',
			'esc_attr_e'           => 'dgettext',
			'esc_html_e'           => 'dgettext',
			'esc_attr_x'           => 'dgettext',
			'esc_html_x'           => 'pgettext',
			'comments_number_link' => 'ngettext',
		],
	];

	/**
	 * {@inheritdoc}
	 */
	public static function fromString( $string, Translations $translations, array $options = [] ) {
		$options += static::$options;

		// Make sure a relative file path is added as a comment.
		$options['file'] = ltrim( str_replace( static::$dir, '', $options['file'] ), '/' );

		$functions = new WordPressFunctionsScanner( $string );

		if ( $options['extractComments'] !== false ) {
			$functions->enableCommentsExtraction( $options['extractComments'] );
		}

		$functions->saveGettextFunctions( $translations, $options );
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
