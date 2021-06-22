<?php

namespace WP_CLI\I18n;

use Gettext\Extractors\Extractor;
use Gettext\Extractors\ExtractorInterface;
use Gettext\Translations;
use WP_CLI;

final class ThemeJsonExtractor extends Extractor implements ExtractorInterface {
	use IterableCodeExtractor;

	/**
	 * @inheritdoc
	 */
	public static function fromString( $string, Translations $translations, array $options = [] ) {
		$file = $options['file'];
		WP_CLI::debug( "Parsing file {$file}", 'make-pot' );

		$theme_json = json_decode( $string, true );

		if ( null === $theme_json ) {
			WP_CLI::debug(
				sprintf(
					'Could not parse file %1$s: error code %2$s',
					$file,
					json_last_error()
				),
				'make-pot'
			);

			return;
		}

		$fields = self::get_fields_to_translate();
		foreach ( $fields as $field ) {
			$path    = $field['path'];
			$key     = $field['key'];
			$context = $field['context'];

			/*
			 * We need to process the paths that include '*' separately.
			 * One example of such a path would be:
			 * [ 'settings', 'blocks', '*', 'color', 'palette' ]
			 */
			$nodes_to_iterate = array_keys( $path, '*', true );
			if ( ! empty( $nodes_to_iterate ) ) {
				/*
				 * At the moment, we only need to support one '*' in the path, so take it directly.
				 * - base will be [ 'settings', 'blocks' ]
				 * - data will be [ 'color', 'palette' ]
				 */
				$base_path = array_slice( $path, 0, $nodes_to_iterate[0] );
				$data_path = array_slice( $path, $nodes_to_iterate[0] + 1 );
				$base_tree = self::array_get( $theme_json, $base_path, array() );
				foreach ( $base_tree as $node_name => $node_data ) {
					$array_to_translate = self::array_get( $node_data, $data_path, null );
					if ( is_null( $array_to_translate ) ) {
						continue;
					}

					foreach ( $array_to_translate as $item_key => $item_to_translate ) {
						if ( empty( $item_to_translate[ $key ] ) ) {
							continue;
						}

						$translation = $translations->insert( $context, $array_to_translate[ $item_key ][ $key ] );
						$translation->addReference( $file );
					}
				}
			} else {
				$array_to_translate = self::array_get( $theme_json, $path, null );
				if ( is_null( $array_to_translate ) ) {
					continue;
				}

				foreach ( $array_to_translate as $item_key => $item_to_translate ) {
					if ( empty( $item_to_translate[ $key ] ) ) {
						continue;
					}

					$translation = $translations->insert( $context, $array_to_translate[ $item_key ][ $key ] );
					$translation->addReference( $file );
				}
			}
		}
	}

	/**
	 * Given a file path, reads it as a JSON file
	 * and returns an array with its contents.
	 *
	 * Returns an empty array in case of error.
	 *
	 * Ported from the core class `WP_Theme_JSON_Resolver`.
	 *
	 * @param string $file_path Path to file.
	 * @return array Contents of the file.
	 */
	private static function read_json_file( $file_path ) {
		$config = array();
		if ( $file_path ) {
			$decoded_file = json_decode(
				file_get_contents( $file_path ),
				true
			);

			$json_decoding_error = json_last_error();
			if ( JSON_ERROR_NONE !== $json_decoding_error ) {
				WP_CLI::debug( "Error when decoding {$file_path}", 'make-pot' );

				return $config;
			}

			if ( is_array( $decoded_file ) ) {
				$config = $decoded_file;
			}
		}
		return $config;
	}

	/**
	 * Returns a data structure to help setting up translations for theme.json data.
	 *
	 * array(
	 *     array(
	 *         'path'    => array( 'settings', 'color', 'palette' ),
	 *         'key'     => 'key-that-stores-the-string-to-translate',
	 *         'context' => 'translation-context',
	 *     ),
	 *     array(
	 *         'path'    => 'etc',
	 *         'key'     => 'etc',
	 *         'context' => 'etc',
	 *     ),
	 * )
	 *
	 * Ported from the core class `WP_Theme_JSON_Resolver`.
	 *
	 * @return array An array of theme.json fields that are translatable and the keys that are translatable.
	 */
	private static function get_fields_to_translate() {
		$file_structure  = self::read_json_file( __DIR__ . '/theme-i18n.json' );
		$theme_json_i18n = self::extract_paths_to_translate( $file_structure );
		return $theme_json_i18n;
	}

	/**
	 * Converts a tree as in theme-i18.json file a linear array
	 * containing metadata to translate a theme.json file.
	 *
	 * For example, given this input:
	 *
	 *     {
	 *       "settings": {
	 *         "*": {
	 *           "typography": {
	 *             "fontSizes": [ { "name": "Font size name" } ],
	 *             "fontStyles": [ { "name": "Font size name" } ]
	 *           }
	 *         }
	 *       }
	 *     }
	 *
	 * will return this output:
	 *
	 *     array(
	 *       0 => array(
	 *         'path'    => array( 'settings', '*', 'typography', 'fontSizes' ),
	 *         'key'     => 'name',
	 *         'context' => 'Font size name'
	 *       ),
	 *       1 => array(
	 *         'path'    => array( 'settings', '*', 'typography', 'fontStyles' ),
	 *         'key'     => 'name',
	 *         'context' => 'Font style name'
	 *       )
	 *     )
	 *
	 * Ported from the core class `WP_Theme_JSON_Resolver`.
	 *
	 * @param array $i18n_partial A tree that follows the format of theme-i18n.json.
	 * @param array $current_path Keeps track of the path as we walk down the given tree.
	 * @return array A linear array containing the paths to translate.
	 */
	private static function extract_paths_to_translate( $i18n_partial, $current_path = array() ) {
		$result = array();
		foreach ( $i18n_partial as $property => $partial_child ) {
			if ( is_numeric( $property ) ) {
				foreach ( $partial_child as $key => $context ) {
					return array(
						array(
							'path'    => $current_path,
							'key'     => $key,
							'context' => $context,
						),
					);
				}
			}
			$result = array_merge(
				$result,
				self::extract_paths_to_translate( $partial_child, array_merge( $current_path, array( $property ) ) )
			);
		}
		return $result;
	}

	/**
	 * Accesses an array in depth based on a path of keys.
	 *
	 * It is the PHP equivalent of JavaScript's `lodash.get()` and mirroring it may help other components
	 * retain some symmetry between client and server implementations.
	 *
	 * Example usage:
	 *
	 *     $array = array(
	 *         'a' => array(
	 *             'b' => array(
	 *                 'c' => 1,
	 *             ),
	 *         ),
	 *     );
	 *     array_get( $array, array( 'a', 'b', 'c' ) );
	 *
	 * @param array $array   An array from which we want to retrieve some information.
	 * @param array $path    An array of keys describing the path with which to retrieve information.
	 * @param mixed $default The return value if the path does not exist within the array,
	 *                       or if `$array` or `$path` are not arrays.
	 * @return mixed The value from the path specified.
	 */
	private static function array_get( $array, $path, $default = null ) {
		// Confirm $path is valid.
		if ( ! is_array( $path ) || 0 === count( $path ) ) {
			return $default;
		}

		foreach ( $path as $path_element ) {
			if (
				! is_array( $array ) ||
				( ! is_string( $path_element ) && ! is_integer( $path_element ) && ! is_null( $path_element ) ) ||
				! array_key_exists( $path_element, $array )
			) {
				return $default;
			}
			$array = $array[ $path_element ];
		}

		return $array;
	}

}
