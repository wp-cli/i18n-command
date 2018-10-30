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
	 * @param SplFileInfo $file     File or directory.
	 * @param array       $matchers List of files and directories to match.
	 * @return int How strongly the file is matched.
	 */
	protected static function calculateMatchScore( SplFileInfo $file, array $matchers = [] ) {
		if ( empty( $matchers ) ) {
			return 0;
		}

		if ( in_array( $file->getBasename(), $matchers, true ) ) {
			return 10;
		}

		// Check for more complex paths, e.g. /some/sub/folder.
		$root_relative_path = str_replace( static::$dir, '', $file->getPathname() );

		foreach ( $matchers as $path_or_file ) {
			$pattern = preg_quote( str_replace( '*', '__wildcard__', $path_or_file ) );
			$pattern = '(^|/)' . str_replace( '__wildcard__', '(.+)', $pattern );

			$base_score = count(
				array_filter(
					explode( '/', $path_or_file ),
					function ( $component) { return $component !== '*'; }
				)
			);
			if ( $base_score === 0 ) {
				$base_score = 0.2;
			}


			if (
				false === strpos( $path_or_file, '*' ) &&
				false !== mb_ereg( $pattern . '$', $root_relative_path )
			) {
				return $base_score * 10;
			}

			if ( false !== mb_ereg( $pattern . '(/|$)', $root_relative_path ) ) {
				return $base_score;
			}
		}

		return 0;
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

					if ( $iterator->hasChildren() ) {
						return true;
					}

					// If no $include is passed everything gets the weakest possible matching score.
					$inclusion_score = empty( $include ) ? 0.1 : static::calculateMatchScore( $file, $include );
					$exclusion_score = static::calculateMatchScore( $file, $exclude );

					if ( $inclusion_score === 0 || $exclusion_score > $inclusion_score ) {
						return false;
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
