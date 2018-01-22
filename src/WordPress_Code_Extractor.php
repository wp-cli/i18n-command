<?php

namespace WP_CLI\I18n;

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
			'__'              => 'text_domain',
			'esc_attr__'      => 'text_domain',
			'esc_html__'      => 'text_domain',
			'_e'              => 'text_domain',
			'esc_attr_e'      => 'text_domain',
			'esc_html_e'      => 'text_domain',
			'_x'              => 'text_context_domain',
			'_ex'             => 'text_context_domain',
			'esc_attr_x'      => 'text_context_domain',
			'esc_html_x'      => 'text_context_domain',
			'_n'              => 'single_plural_number_domain',
			'_nx'             => 'single_plural_number_context_domain',
			'_n_noop'         => 'single_plural_domain',
			'_nx_noop'        => 'single_plural_context_domain',

			// Compat.
			'_'               => 'gettext', // Same as 'text_domain'.

			// Deprecated.
			'_c'              => 'text_domain',
			'_nc'             => 'single_plural_number_domain',
			'__ngettext'      => 'single_plural_number_domain',
			'__ngettext_noop' => 'single_plural_domain',
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
