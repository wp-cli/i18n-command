<?php

namespace WP_CLI\I18n;

use Gettext\Generator\MoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Generator\PoGenerator;
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
	 * [--domain=<domain>]
	 * : Text domain to use for the JSON file name. Overrides the default one extracted from the PO file.
	 *
	 * [--extensions=<extensions>]
	 * : Additional custom JS extensions, comma separated list. By default searches for .min.js and .js extensions.
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
	 * [--use-map=<paths_or_maps>]
	 * : Whether to use a mapping file for the strings, as a JSON value, array to specify multiple.
	 * Each element can either be a string (file path) or object (map).
	 *
	 * ## EXAMPLES
	 *
	 *     # Create JSON files for all PO files in the languages directory
	 *     $ wp i18n make-json languages
	 *
	 *     # Create JSON files for my-plugin-de_DE.po and leave the PO file untouched.
	 *     $ wp i18n make-json my-plugin-de_DE.po /tmp --no-purge
	 *
	 *     # Create JSON files with mapping
	 *     $ wp i18n make-json languages --use-map=build/map.json
	 *
	 *     # Create JSON files with multiple mappings
	 *     $ wp i18n make-json languages '--use-map=["build/map.json","build/map2.json"]'
	 *
	 *     # Create JSON files with object mapping
	 *     $ wp i18n make-json languages '--use-map={"source/index.js":"build/index.js"}'
	 *
	 * @when before_wp_load
	 *
	 * @throws WP_CLI\ExitException
	 */
	public function __invoke( $args, $assoc_args ) {
		$assoc_args      = Utils\parse_shell_arrays( $assoc_args, array( 'use-map' ) );
		$purge           = Utils\get_flag_value( $assoc_args, 'purge', true );
		$update_mo_files = Utils\get_flag_value( $assoc_args, 'update-mo-files', true );
		$map_paths       = Utils\get_flag_value( $assoc_args, 'use-map', false );
		$domain          = Utils\get_flag_value( $assoc_args, 'domain', '' );
		$extensions      = array_map(
			function ( $extension ) {
				return trim( $extension, ' .' );
			},
			explode( ',', Utils\get_flag_value( $assoc_args, 'extensions', '' ) )
		);

		if ( Utils\get_flag_value( $assoc_args, 'pretty-print', false ) ) {
			$this->json_options |= JSON_PRETTY_PRINT;
		}

		$source = realpath( $args[0] );

		if ( ! $source || ( ! is_file( $source ) && ! is_dir( $source ) ) ) {
			WP_CLI::error( 'Source file or directory does not exist.' );
		}

		$destination = is_file( $source ) ? dirname( $source ) : $source;

		if ( isset( $args[1] ) ) {
			$destination = $args[1];
		}

		$map = $this->build_map( $map_paths );
		if ( is_array( $map ) && empty( $map ) ) {
			WP_CLI::error( 'No valid keys found. No file was created.' );
		}

		if ( ! is_dir( $destination ) && ! mkdir( $destination, 0777, true ) ) {
			WP_CLI::error( 'Could not create destination directory.' );
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
				$result        = $this->make_json( $file->getRealPath(), $destination, $map, $domain, $extensions );
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

						$translations = ( new PoLoader() )->loadFile( $file->getPathname() );
						if ( ! ( new MoGenerator() )->generateFile( $translations, $destination_file ) ) {
							WP_CLI::warning( "Could not create file {$destination_file}" );
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
	 * @param string|array|bool $paths_or_maps argument. False to do nothing.
	 * @return array|null       Mapping array. Null if no maps specified.
	 */
	protected function build_map( $paths_or_maps ) {
		if ( false === $paths_or_maps ) {
			return null;
		}

		$map = [];

		// not an array: single value could also be object (associative array)
		if ( ! is_array( $paths_or_maps ) || empty( array_filter( array_keys( $paths_or_maps ), 'is_int' ) ) ) {
			$paths_or_maps = [ $paths_or_maps ];
		}
		$paths = array_filter( $paths_or_maps, 'is_string' );
		WP_CLI::debug( sprintf( 'Using %d map files: %s', count( $paths ), implode( ', ', $paths ) ), 'make-json' );
		$maps = array_filter( $paths_or_maps, 'is_array' );
		WP_CLI::debug( sprintf( 'Using %d inline map objects', count( $maps ) ), 'make-json' );
		WP_CLI::debug( sprintf( 'Dropping %d invalid values from map argument', count( $paths_or_maps ) - count( $paths ) - count( $maps ) ), 'make-json' );

		$to_transform = array_map(
			static function ( $value, $index ) {
				return [ $value, sprintf( 'inline object %d', $index ) ];
			},
			$maps,
			array_keys( $maps )
		);

		foreach ( $paths as $path ) {
			if ( ! file_exists( $path ) || is_dir( $path ) ) {
				WP_CLI::warning( sprintf( 'Map file %s does not exist', $path ) );
				continue;
			}

			$json = json_decode( file_get_contents( $path ), true );
			if ( ! is_array( $json ) ) {
				WP_CLI::warning( sprintf( 'Map file %s invalid', $path ) );
				continue;
			}

			$to_transform[] = [ $json, $path ];
		}

		foreach ( $to_transform as $transform ) {
			list( $json, $file ) = $transform;
			$key_num             = count( $json );
			// normalize contents to string[]
			$json = array_map(
				static function ( $value ) {
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
			WP_CLI::debug( sprintf( 'Dropped %d keys from %s', count( $json ) - $key_num, $file ), 'make-json' );

			$map = array_merge_recursive( $map, $json );
		}

		return $map;
	}

	/**
	 * Splits a single PO file into multiple JSON files.
	 *
	 * @param string     $source_file Path to the source file.
	 * @param string     $destination Path to the destination directory.
	 * @param array|null $map         Source to build file mapping.
	 * @param string     $domain      Override text domain to use.
	 * @param array      $extensions  Additional extensions.
	 * @return array     List of created JSON files.
	 */
	protected function make_json( $source_file, $destination, $map, $domain, $extensions ) {
		/** @var array<string, Translations[]> $mapping */
		$mapping    = [];
		$result     = [];
		$extensions = array_merge( [ 'js' ], $extensions );

		$translations = ( new PoLoader() )->loadFile( $source_file );

		$base_file_name = basename( $source_file, '.po' );

		$domain = ( ! empty( $domain ) ) ? $domain : $translations->getDomain();

		if ( $domain && 0 !== strpos( $base_file_name, $domain ) ) {
			$base_file_name = "{$domain}-{$base_file_name}";
		}

		foreach ( $translations as $translation ) {
			/** @var Translation $translation */

			// Find all unique sources this translation originates from.
			$sources = array_map(
				static function ( $reference ) use ( $extensions ) {
					$file      = $reference[0];
					$extension = pathinfo( $file, PATHINFO_EXTENSION );

					return in_array( $extension, $extensions, true )
						? preg_replace( "/.min.{$extension}$/", ".{$extension}", $file )
						: null;
				},
				$this->reference_map( $translation->getReferences()->toArray(), $map )
			);

			$sources = array_unique( array_filter( $sources ) );

			foreach ( $sources as $source ) {
				if ( ! isset( $mapping[ $source ] ) ) {
					$mapping[ $source ] = Translations::create();

					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Provide code that is meant to be used once the bug is fixed.
					// See https://core.trac.wordpress.org/ticket/45441
					// $mapping[ $source ]->setDomain( $translations->getDomain() );

					$mapping[ $source ]->getHeaders()->set( 'Language', $translations->getLanguage() );
					$mapping[ $source ]->getHeaders()->set( 'PO-Revision-Date', $translations->getHeaders()->get( 'PO-Revision-Date' ) );

					$plural_forms = $translations->getHeaders()->getPluralForm();

					if ( $plural_forms ) {
						list( $count, $rule ) = $plural_forms;
						$mapping[ $source ]->getHeaders()->setPluralForm( $count, $rule );
					}
				}

				$mapping[ $source ]->add( $translation );
			}
		}

		$result += $this->build_json_files( $mapping, $base_file_name, $destination );

		return $result;
	}

	/**
	 * Takes the references and applies map, if given
	 *
	 * @param array      $references Translation references.
	 * @param array|null $map        File mapping.
	 * @return array Mapped references.
	 */
	protected function reference_map( $references, $map ) {
		if ( is_null( $map ) ) {
			return $references;
		}

		// translate using map
		$temp = array_map(
			static function ( $reference ) use ( &$map ) {
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
			if ( is_null( $sources ) ) {
				continue;
			}
			array_push( $references, ...$sources );
		}
		// and wrap to array
		return array_map(
			static function ( $value ) {
				return [ $value ];
			},
			$references
		);
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
			$destination_file = "{$destination}/{$base_file_name}-{$hash}.json";

			$success = ( new JedGenerator( $this->json_options, $file ) )->generateFile(
				$translations,
				$destination_file
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
		$translations = ( new PoLoader() )->loadFile( $source_file );

		foreach ( $translations->getTranslations() as $translation ) {
			/** @var Translation $translation */

			$references = $translation->getReferences();

			if ( 0 === $references->count() ) {
				continue;
			}

			foreach ( $references->toArray() as $reference ) {
				$file = $reference[0];

				if ( substr( $file, - 3 ) !== '.js' ) {
					continue 2;
				}
			}

			unset( $translations[ $translation->getId() ] );
		}

		return ( new PoGenerator() )->generateFile( $translations, $source_file );
	}
}
