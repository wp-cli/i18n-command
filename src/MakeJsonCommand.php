<?php

namespace WP_CLI\I18n;

use Gettext\Extractors\Po as PoExtractor;
use Gettext\Generators\Po as PoGenerator;
use Gettext\Translation;
use Gettext\Translations;
use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils;
use DirectoryIterator;
use IteratorIterator;
use SplFileInfo;

class MakeJsonCommand extends WP_CLI_Command {
	/**
	 * Options passed to json_encode().
	 *
	 * @var int JSON options.
	 */
	protected $json_options = 0;

	/**
	 * Extract JavaScript strings from PO files and add them to individual JSON files.
	 *
	 * For JavaScript internationalization purposes, WordPress requires translations to be split up into
	 * one Jed-formatted JSON file per JavaScript source file.
	 *
	 * See https://make.wordpress.org/core/2018/11/09/new-javascript-i18n-support-in-wordpress/ to learn more
	 * about WordPress JavaScript internationalization.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : Path to an existing PO file or a directory containing multiple PO files.
	 *
	 * [<destination>]
	 * : Path to the destination directory for the resulting JSON files. Defaults to the source directory.
	 *
	 * [--purge]
	 * : Whether to purge the strings that were extracted from the original source file. Defaults to true, use `--no-purge` to skip the removal.
	 *
	 * [--update-mo-files]
	 * : Whether MO files should be updated as well after updating PO files.
	 * Only has an effect when used in combination with `--purge`.
	 *
	 * [--pretty-print]
	 * : Pretty-print resulting JSON files.
	 *
	 * [--use-map[=<paths>]]
	 * : Whether to use a mapping file for the strings. Separate with `,` to specify multiple. Defaults to `build/map.json`.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create JSON files for all PO files in the languages directory
	 *     $ wp i18n make-json languages
	 *
	 *     # Create JSON files for my-plugin-de_DE.po and leave the PO file untouched.
	 *     $ wp i18n make-json my-plugin-de_DE.po /tmp --no-purge
	 *
	 * @when before_wp_load
	 *
	 * @throws WP_CLI\ExitException
	 */
	public function __invoke( $args, $assoc_args ) {
		$purge           = Utils\get_flag_value( $assoc_args, 'purge', true );
		$update_mo_files = Utils\get_flag_value( $assoc_args, 'update-mo-files', true );
		$map_paths       = Utils\get_flag_value( $assoc_args, 'use-map', false );

		if ( Utils\get_flag_value( $assoc_args, 'pretty-print', false ) ) {
			$this->json_options |= JSON_PRETTY_PRINT;
		}

		$source = realpath( $args[0] );

		if ( ! $source || ( ! is_file( $source ) && ! is_dir( $source ) ) ) {
			WP_CLI::error( 'Source file or directory does not exist!' );
		}

		$destination = is_file( $source ) ? dirname( $source ) : $source;

		if ( isset( $args[1] ) ) {
			$destination = $args[1];
		}

		$map = $this->build_map( $map_paths );
		if ( is_array( $map ) && empty( $map ) ) {
			WP_CLI::error( 'No valid keys found. No file was created.' );
		}

		// Two is_dir() checks in case of a race condition.
		if ( ! is_dir( $destination )
			&& ! mkdir( $destination, 0777, true )
			&& ! is_dir( $destination )
		) {
			WP_CLI::error( 'Could not create destination directory!' );
		}

		$result_count = 0;

		if ( is_file( $source ) ) {
			$files = [ new SplFileInfo( $source ) ];
		} else {
			$files = new IteratorIterator( new DirectoryIterator( $source ) );
		}

		/** @var DirectoryIterator $file */
		foreach ( $files as $file ) {
			if ( $file->isFile() && $file->isReadable() && 'po' === $file->getExtension() ) {
				$result        = $this->make_json( $file->getRealPath(), $destination, $map );
				$result_count += count( $result );

				if ( $purge ) {
					$removed = $this->remove_js_strings_from_po_file( $file->getRealPath() );

					if ( ! $removed ) {
						WP_CLI::warning( sprintf( 'Could not update file %s', basename( $source ) ) );
						continue;
					}

					if ( $update_mo_files ) {
						$file_basename    = basename( $file->getFilename(), '.po' );
						$destination_file = "{$destination}/{$file_basename}.mo";

						$translations = Translations::fromPoFile( $file->getPathname() );
						if ( ! $translations->toMoFile( $destination_file ) ) {
							WP_CLI::warning( "Could not create file {$destination_file}" );
							continue;
						}
					}
				}
			}
		}

		WP_CLI::success( sprintf( 'Created %d %s.', $result_count, Utils\pluralize( 'file', $result_count ) ) );
	}

	/**
	 * Collect maps from paths, normalize and merge
	 *
	 * @param string|bool $paths argument. False to do nothing.
	 * @return array|null Mapping array. Null if no maps specified.
	 */
	protected function build_map( $paths ) {
		if ( false === $paths ) {
			return null;
		}

		$map = [];

		if ( true === $paths ) {
			$paths = [ 'build' . DIRECTORY_SEPARATOR . 'map.json' ];
		} else {
			$paths = array_unique( array_filter( explode( ',', $paths ) ) );
		}
		WP_CLI::debug( sprintf( 'Using files %s', implode( ', ', $paths ) ), 'make-json' );

		foreach ( $paths as $path ) {
			if ( ! file_exists( $path ) || is_dir( $path ) ) {
				WP_CLI::warning( sprintf( 'File %s does not exist', $path ) );
				continue;
			}

			$json = json_decode( file_get_contents( $path ), true );
			if ( ! is_array( $json ) ) {
				WP_CLI::warning( sprintf( 'File %s contained invalid map', $path ) );
				continue;
			}

			$key_num = count( $json );
			// normalize contents to string[]
			$json = array_map(
				function ( $value ) {
					if ( is_array( $value ) ) {
						$value = array_values( array_filter( $value, 'is_string' ) );
						if ( ! empty( $value ) ) {
							return $value;
						}
					}

					if ( is_string( $value ) ) {
						return [ $value ];
					}

					return null;
				},
				$json
			);
			WP_CLI::debug( sprintf( 'Dropped %d keys from %s', count( $json ) - $key_num, $path ), 'make-json' );

			$map = array_merge_recursive( $map, $json );
		}

		return $map;
	}

	/**
	 * Splits a single PO file into multiple JSON files.
	 *
	 * @param string $source_file Path to the source file.
	 * @param string $destination Path to the destination directory.
	 * @param array  $map               Source to build file mapping.
	 * @return array List of created JSON files.
	 */
	protected function make_json( $source_file, $destination, $map ) {
		/** @var Translations[] $mapping */
		$mapping      = [];
		$translations = new Translations();
		$result       = [];

		PoExtractor::fromFile( $source_file, $translations );

		$base_file_name = basename( $source_file, '.po' );

		foreach ( $translations as $index => $translation ) {
			/** @var Translation $translation */

			$references = $translation->getReferences();

			if ( ! is_null( $map ) ) {
				// translate using map
				$temp = array_map(
					function ( $reference ) use ( &$map ) {
						$file = $reference[0];

						if ( array_key_exists( $file, $map ) ) {
							return $map[ $file ];
						}

						return null;
					},
					$references
				);
				// this is now an array of arrays of sources, translate to array of sources
				$references = [];
				foreach ( $temp as $sources ) {
					$references = array_merge( $references, $sources );
				}
				// and wrap to array
				$references = array_map(
					function ( $value ) {
						return [ $value ];
					},
					$references
				);
			}

			// Find all unique sources this translation originates from.
			$sources = array_map(
				function ( $reference ) {
					$file = $reference[0];

					if ( substr( $file, - 7 ) === '.min.js' ) {
						return substr( $file, 0, - 7 ) . '.js';
					}

					if ( substr( $file, - 3 ) === '.js' ) {
						return $file;
					}

					return null;
				},
				$references
			);

			$sources = array_unique( array_filter( $sources ) );

			foreach ( $sources as $source ) {
				if ( ! isset( $mapping[ $source ] ) ) {
					$mapping[ $source ] = new Translations();

					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Provide code that is meant to be used once the bug is fixed.
					// See https://core.trac.wordpress.org/ticket/45441
					// $mapping[ $source ]->setDomain( $translations->getDomain() );

					$mapping[ $source ]->setHeader( 'Language', $translations->getLanguage() );
					$mapping[ $source ]->setHeader( 'PO-Revision-Date', $translations->getHeader( 'PO-Revision-Date' ) );

					$plural_forms = $translations->getPluralForms();

					if ( $plural_forms ) {
						list( $count, $rule ) = $plural_forms;
						$mapping[ $source ]->setPluralForms( $count, $rule );
					}
				}

				$mapping[ $source ][] = $translation;
			}
		}

		$result += $this->build_json_files( $mapping, $base_file_name, $destination );

		return $result;
	}

	/**
	 * Builds a mapping of JS file names to translation entries.
	 *
	 * Exports translations for each JS file to a separate translation file.
	 *
	 * @param array  $mapping        A mapping of files to translation entries.
	 * @param string $base_file_name Base file name for JSON files.
	 * @param string $destination    Path to the destination directory.
	 *
	 * @return array List of created JSON files.
	 */
	protected function build_json_files( $mapping, $base_file_name, $destination ) {
		$result = [];

		foreach ( $mapping as $file => $translations ) {
			/** @var Translations $translations */

			$hash             = md5( $file );
			$destination_file = "${destination}/{$base_file_name}-{$hash}.json";

			$success = JedGenerator::toFile(
				$translations,
				$destination_file,
				[
					'json'   => $this->json_options,
					'source' => $file,
				]
			);

			if ( ! $success ) {
				WP_CLI::warning( sprintf( 'Could not create file %s', basename( $destination_file, '.json' ) ) );

				continue;
			}

			$result[] = $destination_file;
		}

		return $result;
	}

	/**
	 * Removes strings from PO file that only occur in JavaScript file.
	 *
	 * @param string $source_file Path to the PO file.
	 * @return bool True on success, false otherwise.
	 */
	protected function remove_js_strings_from_po_file( $source_file ) {
		/** @var Translations[] $mapping */
		$translations = new Translations();

		PoExtractor::fromFile( $source_file, $translations );

		foreach ( $translations->getArrayCopy() as $translation ) {
			/** @var Translation $translation */

			if ( ! $translation->hasReferences() ) {
				continue;
			}

			foreach ( $translation->getReferences() as $reference ) {
				$file = $reference[0];

				if ( substr( $file, - 3 ) !== '.js' ) {
					continue 2;
				}
			}

			unset( $translations[ $translation->getId() ] );
		}

		return PoGenerator::toFile( $translations, $source_file );
	}
}
