<?php

namespace WP_CLI\Makepot;

use Gettext\Extractors\PhpCode;
use Gettext\Translation;
use Gettext\Translations;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_CLI;

class WordPress_Code_Extractor extends PhpCode {
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

		$functions = new WordPress_Functions_Scanner( $string );

		if ( $options['extractComments'] !== false ) {
			$functions->enableCommentsExtraction( $options['extractComments'] );
		}

		$functions->saveGettextFunctions( $translations, $options );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function fromFile( $file, Translations $translations, array $options = [] ) {
		foreach ( self::getFiles( $file ) as $f ) {
			// Make sure a relative file path is added as a comment.
			$options['file'] = ltrim( str_replace( static::$dir, '', $f ), '/' );

			$string = self::readFile( $f );

			if ( $options['wpExtractTemplates'] ) {
				$headers = Makepot_Command::get_file_data_from_string( $string, [ 'Template Name' => 'Template Name' ] );

				if ( ! empty( $headers[ 'Template Name'])) {
					$translation = new Translation( '', $headers[ 'Template Name'] );
					$translation->addExtractedComment('Template Name of the theme' );

					$translations[] = $translation;
				}
			}

			static::fromString( $string, $translations, $options );
		}
	}

	/**
	 * Recursively extracts the translations from a directory.
	 *
	 * @param string $dir Root path to start the recursive traversal in.
	 * @param Translations $translations The translations instance to append the new translations.
	 * @param array $options
	 */
	public static function fromDirectory( $dir, Translations $translations, array $options = [] ) {
		static::$dir = $dir;
		static::fromFile( static::getFilesFromDirectory( $dir ), $translations, $options );
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
		$filtered_files = [];

		try {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			/** @var \DirectoryIterator $file */
			foreach ( $files as $file ) {
				if ( $file->isFile() && 'php' === $file->getExtension()) {
					$filtered_files[] = $file->getPathname();
				}
			}
		} catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		return $filtered_files;
	}
}
