<?php

namespace WP_CLI\I18n;

use Gettext\Translation;
use Gettext\Translations;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use DirectoryIterator;
use SplFileInfo;
use WP_CLI;

trait IterableCodeExtractor {

	private static $dir = '';

	/**
	 * Extract the translations from a file.
	 *
	 * @param array|string $file         A path of a file or files
	 * @param Translations $translations The translations instance to append the new translations.
	 * @param array        $options      {
	 *     Optional. An array of options passed down to static::fromString()
	 *
	 *     @type bool $wpExtractTemplates Extract 'Template Name' headers in theme files. Default 'false'.
	 * }
	 * @return null
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

			if ( ! empty ( $options['wpExtractTemplates'] ) ) {
				$headers = MakePotCommand::get_file_data_from_string( $string, [ 'Template Name' => 'Template Name' ] );

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
	 * Extract the translations from a file.
	 *
	 * @param string $dir                Root path to start the recursive traversal in.
	 * @param Translations $translations The translations instance to append the new translations.
	 * @param array        $options      {
	 *     Optional. An array of options passed down to static::fromString()
	 *
	 *     @type bool $wpExtractTemplates Extract 'Template Name' headers in theme files. Default 'false'.
	 *     @type array $exclude           A list of path to exclude. Default [].
	 *     @type array $extensions        A list of extensions to process. Default [].
	 * }
	 * @return null
	 */
	public static function fromDirectory( $dir, Translations $translations, array $options = [] ) {
		static::$dir = $dir;

		$include = isset( $options['include'] ) ? $options['include'] : [];
		$exclude = isset( $options['exclude'] ) ? $options['exclude'] : [];

		$files = static::getFilesFromDirectory( $dir, $include, $exclude, $options['extensions'] );

		if ( ! empty( $files ) ) {
			static::fromFile( $files, $translations, $options );
		}

		static::$dir = '';
	}

	/**
	 * Determines whether a file is valid based on the include option.
	 *
	 * @param SplFileInfo $file    File or directory.
	 * @param array       $include List of files and directories to include.
	 * @return bool
	 */
	protected static function isIncluded( SplFileInfo $file, array $include = [] ) {
		if ( empty( $include ) ) {
			return true;
		}

		if ( in_array( $file->getBasename(), $include, true ) ) {
			return true;
		}

		// Check for more complex paths, e.g. /some/sub/folder.
		foreach ( $include as $path_or_file ) {
			$pattern = preg_quote( str_replace( '*', '__wildcard__', $path_or_file ) );
			$pattern = '/' . str_replace( '__wildcard__', '(.+)', $pattern );

			if (
				false !== mb_ereg( $pattern, $file->getPathname() . '$' ) &&
				false !== mb_ereg( $pattern, $file->getPathname() . '/' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determines whether a file is valid based on the exclude option.
	 *
	 * @param SplFileInfo $file    File or directory.
	 * @param array       $exclude List of files and directories to skip.
	 * @return bool
	 */
	protected static function isExcluded( SplFileInfo $file, array $exclude = [] ) {
		if ( empty( $exclude ) ) {
			return false;
		}

		if ( in_array( $file->getBasename(), $exclude, true ) ) {
			return true;
		}

		// Check for more complex paths, e.g. /some/sub/folder.
		foreach ( $exclude as $path_or_file ) {
			$pattern = preg_quote( str_replace( '*', '__wildcard__', $path_or_file ) );
			$pattern = '/' . str_replace( '__wildcard__', '(.+)', $pattern ) . '$';

			if ( false !== mb_ereg( $pattern, $file->getPathname() ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively gets all PHP files within a directory.
	 *
	 * @param string $dir A path of a directory.
	 * @param array $include List of files and directories to include.
	 * @param array $exclude List of files and directories to skip.
	 * @param array $extensions List of filename extensions to process.
	 *
	 * @return array File list.
	 */
	public static function getFilesFromDirectory( $dir, array $include = [], array $exclude = [], $extensions = [] ) {
		$filtered_files = [];

		$files = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				function ( $file, $key, $iterator ) use ( $include, $exclude, $extensions ) {
					/** @var RecursiveCallbackFilterIterator $iterator */
					/** @var SplFileInfo $file */

					if ( static::isExcluded( $file, $exclude ) && ( empty( $include ) || ! static::isIncluded( $file, $include ) ) ) {
						return false;
					}

					if ( ! static::isIncluded( $file, $include ) && ! $iterator->hasChildren() ) {
						return false;
					}

					if ( $iterator->hasChildren() ) {
						return true;
					}

					return ( $file->isFile() && in_array( $file->getExtension(), $extensions, true ) );
				}
			),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $file ) {
			/** @var SplFileInfo $file */
			if ( ! $file->isFile() || ! in_array( $file->getExtension(), $extensions, true ) ) {
				continue;
			}

			$filtered_files[] = $file->getPathname();
		}

		return $filtered_files;
	}
}
