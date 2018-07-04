<?php

namespace WP_CLI\I18n;

use Gettext\Extractors\JsCode;
use Gettext\Translations;
use Peast\Syntax\Exception as PeastException;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use DirectoryIterator;
use WP_CLI;

final class JsCodeExtractor extends JsCode {
	private static $dir = '';

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
		foreach ( static::getFiles( $file ) as $f ) {
			// Make sure a relative file path is added as a comment.
			$options['file'] = ltrim( str_replace( static::$dir, '', $f ), '/' );

			$string = file_get_contents( $f );

			if ( ! $string ) {
				WP_CLI::debug(
					sprintf(
						'Could not load file %1s',
						$f
					)
				);

				continue;
			}

			try {
				static::fromString( $string, $translations, $options );
			} catch ( PeastException $e ) {
				WP_CLI::debug(
					sprintf(
						'Could not parse file %1$s: %2$s (line %3$d, column %4$d)',
						$options['file'],
						$e->getMessage(),
						$e->getPosition()->getLine(),
						$e->getPosition()->getColumn()
					)
				);
			}
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

		$files = static::getFilesFromDirectory( $dir, isset( $options['exclude'] ) ? $options['exclude'] : [] );

		if ( ! empty( $files ) ) {
			static::fromFile( $files, $translations, $options );
		}

		static::$dir = '';
	}

	/**
	 * Recursively gets all PHP files within a directory.
	 *
	 * @param string $dir A path of a directory.
	 * @param array $exclude List of files and directories to skip.
	 *
	 * @return array File list.
	 */
	private static function getFilesFromDirectory( $dir, array $exclude = [] ) {
		$filtered_files = [];

		$files = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				function ( $file, $key, $iterator ) use ( $exclude ) {
					/** @var DirectoryIterator $file */
					if ( in_array( $file->getBasename(), $exclude, true ) ) {
						return false;
					}

					// Check for more complex paths, e.g. /some/sub/folder.
					foreach( $exclude as $path_or_file ) {
						if ( false !== mb_ereg( preg_quote( '/' . $path_or_file ) . '$', $file->getPathname() ) ) {
							return false;
						}
					}

					/** @var RecursiveCallbackFilterIterator $iterator */
					if ( $iterator->hasChildren() ) {
						return true;
					}

					return ( $file->isFile() && 'js' === $file->getExtension() );
				}
			),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $file ) {
			/** @var DirectoryIterator $file */
			if ( ! $file->isFile() || 'js' !== $file->getExtension() ) {
				continue;
			}

			$filtered_files[] = $file->getPathname();
		}

		return $filtered_files;
	}
}
