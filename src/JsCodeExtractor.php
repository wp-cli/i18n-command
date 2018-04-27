<?php

namespace WP_CLI\I18n;

use Gettext\Extractors\JsCode;
use Gettext\Translations;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_CLI;

class JsCodeExtractor extends JsCode {
	protected static $dir = '';

	public static $options = [
		'extractComments' => [ 'translators', 'Translators' ],
		'constants'       => [],
		'functions'       => [
			'__'  => 'text_domain',
			'_x'  => 'text_context_domain',
			'_n'  => 'single_plural_number_domain',
			'_nx' => 'single_plural_number_context_domain',
		],
	];

	/**
	 * {@inheritdoc}
	 */
	public static function fromString( $string, Translations $translations, array $options = [] ) {
		$options += static::$options;

		$functions = new JsFunctionsScanner( $string );

		$functions->enableCommentsExtraction( $options['extractComments'] );
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

		$files = static::getFilesFromDirectory( $dir );

		if ( ! empty( $files ) ) {
			static::fromFile( $files, $translations, $options );
		}

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

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		/* @var \DirectoryIterator $file */
		foreach ( $files as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			if ( $file->isFile() && 'js' === $file->getExtension() ) {
				$filtered_files[] = $file->getPathname();
			}
		}

		return $filtered_files;
	}
}
