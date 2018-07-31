<?php

namespace WP_CLI\I18n;

use Gettext\Extractors\Po;
use Gettext\Merge;
use Gettext\Translation;
use Gettext\Translations;
use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils;
use DirectoryIterator;
use IteratorIterator;

class MakePotCommand extends WP_CLI_Command {
	/**
	 * @var  Translations
	 */
	protected $translations;

	/**
	 * @var string
	 */
	protected $source;

	/**
	 * @var string
	 */
	protected $destination;

	/**
	 * @var string
	 */
	protected $merge;

	/**
	 * @var array
	 */
	protected $exclude = [ 'node_modules', '.git', '.svn', '.CVS', '.hg', 'vendor', 'Gruntfile.js', 'webpack.config.js' ];

	/**
	 * @var string
	 */
	protected $slug;

	/**
	 * @var array
	 */
	protected $main_file_data = [];

	/**
	 * @var bool
	 */
	protected $skip_js = false;

	/**
	 * @var array
	 */
	protected $headers = [];

	/**
	 * @var string
	 */
	protected $domain;

	/**
	 * These Regexes copied from http://php.net/manual/en/function.sprintf.php#93552
	 * and adjusted for better precision and updated specs.
	 */
	const SPRINTF_PLACEHOLDER_REGEX = '/(?:
		(?<!%)                     # Don\'t match a literal % (%%).
		(
			%                          # Start of placeholder.
			(?:[0-9]+\$)?              # Optional ordering of the placeholders.
			[+-]?                      # Optional sign specifier.
			(?:
				(?:0|\'.)?                 # Optional padding specifier - excluding the space.
				-?                         # Optional alignment specifier.
				[0-9]*                     # Optional width specifier.
				(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
				|                      # Only recognize the space as padding in combination with a width specifier.
				(?:[ ])?                   # Optional space padding specifier.
				-?                         # Optional alignment specifier.
				[0-9]+                     # Width specifier.
				(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
			)
			[bcdeEfFgGosuxX]           # Type specifier.
		)
	)/x';

	/**
	 * "Unordered" means there's no position specifier: '%s', not '%2$s'.
	 */
	const UNORDERED_SPRINTF_PLACEHOLDER_REGEX = '/(?:
		(?<!%)                     # Don\'t match a literal % (%%).
		%                          # Start of placeholder.
		[+-]?                      # Optional sign specifier.
		(?:
			(?:0|\'.)?                 # Optional padding specifier - excluding the space.
			-?                         # Optional alignment specifier.
			[0-9]*                     # Optional width specifier.
			(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
			|                      # Only recognize the space as padding in combination with a width specifier.
			(?:[ ])?                   # Optional space padding specifier.
			-?                         # Optional alignment specifier.
			[0-9]+                     # Width specifier.
			(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
		)
		[bcdeEfFgGosuxX]           # Type specifier.
	)/x';

	/**
	 * Create a POT file for a WordPress plugin or theme.
	 *
	 * Scans PHP and JavaScript files, as well as theme stylesheets for translatable strings.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : Directory to scan for string extraction.
	 *
	 * [<destination>]
	 * : Name of the resulting POT file.
	 *
	 * [--slug=<slug>]
	 * : Plugin or theme slug. Defaults to the source directory's basename.
	 *
	 * [--domain=<domain>]
	 * : Text domain to look for in the source code, unless the `--ignore-domain` option is used.
	 * By default, the "Text Domain" header of the plugin or theme is used.
	 * If none is provided, it falls back to the plugin/theme slug.
	 *
	 * [--ignore-domain]
	 * : Ignore the text domain completely and extract strings with any text domain.
	 *
	 * [--merge[=<file>]]
	 * : Existing POT file file whose content should be merged with the extracted strings.
	 * If left empty, defaults to the destination POT file.
	 *
	 * [--exclude=<paths>]
	 * : Include additional ignored paths as CSV (e.g. 'tests,bin,.github').
	 * By default, the following files and folders are ignored: node_modules, .git, .svn, .CVS, .hg, vendor.
	 * Leading and trailing slashes are ignored, i.e. `/my/directory/` is the same as `my/directory`.
	 *
	 * [--headers=<headers>]
	 * : Array in JSON format of custom headers which will be added to the POT file. Defaults to empty array.
	 *
	 * [--skip-js]
	 * : Skips JavaScript string extraction. Useful when this is done in another build step, e.g. through Babel.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create a POT file for the WordPress plugin/theme in the current directory
	 *     $ wp i18n make-pot . languages/my-plugin.pot
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		$this->handle_arguments( $args, $assoc_args );
		if ( ! $this->makepot() ) {
			WP_CLI::error( 'Could not generate a POT file!' );
		}

		WP_CLI::success( 'POT file successfully generated!' );
	}

	/**
	 * Process arguments from command-line in a reusable way.
	 */
	public function handle_arguments( $args, $assoc_args ) {
		$array_arguments = array( 'headers' );
		$assoc_args      = \WP_CLI\Utils\parse_shell_arrays( $assoc_args, $array_arguments );
		$this->source    = realpath( $args[0] );
		$this->slug      = Utils\get_flag_value( $assoc_args, 'slug', Utils\basename( $this->source ) );
		$this->skip_js   = Utils\get_flag_value( $assoc_args, 'skip-js', $this->skip_js );
		$this->headers   = Utils\get_flag_value( $assoc_args, 'headers', $this->headers );

		$ignore_domain = Utils\get_flag_value( $assoc_args, 'ignore-domain', false );

		if ( ! $this->source || ! is_dir( $this->source ) ) {
			WP_CLI::error( 'Not a valid source directory!' );
		}

		$this->retrieve_main_file_data();

		$file_data = $this->get_main_file_data();

		if ( ! $ignore_domain ) {
			$this->domain = $this->slug;

			if ( ! empty( $file_data['Text Domain'] ) ) {
				$this->domain = $file_data['Text Domain'];
			}

			$this->domain = Utils\get_flag_value( $assoc_args, 'domain', $this->domain );
		}

		// Determine destination.
		$this->destination = "{$this->source}/{$this->slug}.pot";

		if ( ! empty( $file_data['Domain Path'] ) ) {
			// Domain Path inside source folder.
			$this->destination = sprintf( '%s/%s/%s.pot', $this->source, $file_data['Domain Path'], $this->slug );
		}

		if ( isset( $args[1] ) ) {
			$this->destination = $args[1];
		}

		// Two is_dir() checks in case of a race condition.
		if ( ! is_dir( dirname( $this->destination ) ) && ! mkdir( dirname( $this->destination ) ) && ! is_dir( dirname( $this->destination ) ) ) {
			WP_CLI::error( 'Could not create destination directory!' );
		}

		if ( isset( $assoc_args['merge'] ) ) {
			if ( true === $assoc_args['merge'] && file_exists( $this->destination ) ) {
				$this->merge = $this->destination;
			} elseif ( ! empty( $assoc_args['merge'] ) ) {
				if ( ! file_exists( $assoc_args['merge'] ) ) {
					WP_CLI::error( sprintf( 'Invalid file %s', $assoc_args['merge'] ) );
				}

				$this->merge = $assoc_args['merge'];
			}
		}

		WP_CLI::debug( sprintf( 'Destination: %s', $this->destination ), 'make-pot' );

		if ( isset( $assoc_args['exclude'] ) ) {
			$this->exclude = array_filter( array_merge( $this->exclude, explode( ',', $assoc_args['exclude'] ) ) );
			$this->exclude = array_map( [ $this, 'unslashit' ], $this->exclude);
			$this->exclude = array_unique( $this->exclude );
		}
	}

	/**
	 * Removes leading and trailing slashes of a string.
	 *
	 * @param string $string What to add the remove slashes from.
	 * @return string String without leading and trailing slashes.
	 */
	protected function unslashit( $string ) {
		return ltrim( rtrim( $string, '/\\' ), '/\\' );
	}

	/**
	 * Retrieves the main file data of the plugin or theme.
	 *
	 * @return void
	 */
	protected function retrieve_main_file_data() {
		if ( is_file( "$this->source/style.css" ) && is_readable( "$this->source/style.css" ) ) {
			$theme_data = static::get_file_data( "$this->source/style.css", array_combine( $this->get_file_headers( 'theme' ), $this->get_file_headers( 'theme' ) ) );

			// Stop when it contains a valid Theme Name header.
			if ( ! empty( $theme_data['Theme Name'] ) ) {
				WP_CLI::log( 'Theme stylesheet detected.' );

				$this->main_file_data = $theme_data;

				return;
			}
		}

		$plugin_files = [];

		$files = new IteratorIterator( new DirectoryIterator( $this->source ) );

		/** @var DirectoryIterator $file */
		foreach ( $files as $file ) {
			if ( $file->isFile() && $file->isReadable() && 'php' === $file->getExtension()) {
				$plugin_files[] = $file->getRealPath();
			}
		}

		foreach ( $plugin_files as $plugin_file ) {
			$plugin_data = static::get_file_data( $plugin_file, array_combine( $this->get_file_headers( 'plugin' ), $this->get_file_headers( 'plugin' ) ) );

			// Stop when we find a file with a valid Plugin Name header.
			if ( ! empty( $plugin_data['Plugin Name'] ) ) {
				WP_CLI::log( 'Plugin file detected.' );
				WP_CLI::debug( sprintf( 'Plugin file: %s', $plugin_file ), 'make-pot' );

				$this->main_file_data = $plugin_data;

				return;
			}
		}

		WP_CLI::error( 'No valid theme stylesheet or plugin file found!' );
	}

	/**
	 * Returns the file headers for themes and plugins.
	 *
	 * @param string $type Source type, either theme or plugin.
	 *
	 * @return array List of file headers.
	 */
	protected function get_file_headers( $type ) {
		switch ( $type ) {
			case 'plugin':
				return [
					'Plugin Name',
					'Plugin URI',
					'Description',
					'Author',
					'Author URI',
					'Version',
					'Domain Path',
					'Text Domain',
				];
			case 'theme':
				return [
					'Theme Name',
					'Theme URI',
					'Description',
					'Author',
					'Author URI',
					'Version',
					'License',
					'Domain Path',
					'Text Domain',
				];
			default:
				return [];
		}
	}

	/**
	 * Returns the header data of the main plugin/theme file.
	 *
	 * @return array Main file data.
	 */
	protected function get_main_file_data() {
		return $this->main_file_data;
	}

	/**
	 * Creates a POT file and stores it on disk.
	 *
	 * @return bool True on success, false otherwise.
	 */
	protected function makepot() {
		$this->translations = new Translations();

		// Add existing strings first but don't keep headers.
		if ( $this->merge ) {
			$existing_translations = new Translations();
			Po::fromFile( $this->merge, $existing_translations );
			$this->translations->mergeWith( $existing_translations, Merge::ADD | Merge::REMOVE );
		}

		$meta = $this->get_meta_data();
		PotGenerator::setCommentBeforeHeaders( $meta['comments'] );

		$this->set_default_headers();

		// POT files have no Language header.
		$this->translations->deleteHeader( Translations::HEADER_LANGUAGE );

		if ( $this->domain ) {
			$this->translations->setDomain( $this->domain );
		}

		$file_data = $this->get_main_file_data();

		unset( $file_data['Version'], $file_data['License'], $file_data['Domain Path'], $file_data['Text Domain'] );

		// Set entries from main file data.
		foreach ( $file_data as $header => $data ) {
			if ( empty( $data ) ) {
				continue;
			}

			$translation = new Translation( '', $data );

			if ( isset( $file_data['Theme Name'] ) ) {
				$translation->addExtractedComment( sprintf( '%s of the theme', $header ) );
			} else {
				$translation->addExtractedComment( sprintf( '%s of the plugin', $header ) );
			}

			$this->translations[] = $translation;
		}

		try {
			PhpCodeExtractor::fromDirectory( $this->source, $this->translations, [
				// Extract 'Template Name' headers in theme files.
				'wpExtractTemplates' => isset( $file_data['Theme Name'] ),
				'exclude'            => $this->exclude,
				'extensions'         => [ 'php' ],
			] );

			if ( ! $this->skip_js ) {
				JsCodeExtractor::fromDirectory(
					$this->source,
					$this->translations,
					[
						'exclude' => $this->exclude,
						'extensions' => [ 'js' ],
					]
				);
			}
		} catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$this->audit_strings();

		return PotGenerator::toFile( $this->translations, $this->destination );
	}

	/**
	 * Audits strings.
	 *
	 * Goes through all extracted strings to find possible mistakes.
	 */
	protected function audit_strings() {
		foreach( $this->translations as $translation ) {
			/** @var Translation $translation */

			$references = $translation->getReferences();
			$location   = implode( ':', array_shift( $references ) );

			if ( $translation->hasExtractedComments() ) {
				$comments = $translation->getExtractedComments();
				$comments_count = count( $comments );

				if ( $comments_count > 1 ) {
					WP_CLI::warning( sprintf(
						'The string "%1$s" has %2$d different translator comments. (%3$s)',
						$translation->getOriginal(),
						$comments_count,
						$location
					) );
				}
			}

			$non_placeholder_content = trim( preg_replace( '`^([\'"])(.*)\1$`Ds', '$2', $translation->getOriginal() ) );
			$non_placeholder_content = preg_replace( self::SPRINTF_PLACEHOLDER_REGEX, '', $non_placeholder_content );

			if ( '' === $non_placeholder_content ) {
				WP_CLI::warning( sprintf(
					'Found string without translatable content (%s)',
					$location
				) );
			}

			if ( $translation->hasPlural() ) {
				preg_match_all( self::SPRINTF_PLACEHOLDER_REGEX, $translation->getOriginal(), $single_placeholders );
				$single_placeholders = $single_placeholders[0];

				preg_match_all( self::SPRINTF_PLACEHOLDER_REGEX, $translation->getPlural(), $plural_placeholders );
				$plural_placeholders = $plural_placeholders[0];

				// see https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#plurals
				if ( count( $single_placeholders ) < \count( $plural_placeholders ) ) {
					WP_CLI::warning( sprintf(
						'Missing singular placeholder, needed for some languages. See https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#plurals (%s)',
						$location
					) );
				} else {
					sort( $single_placeholders );
					sort( $plural_placeholders );

					if ( $single_placeholders !== $plural_placeholders ) {
						WP_CLI::warning( sprintf(
							'Singular and plural placeholder appear in different order (%s)',
							$location
						) );
					}
				}

				// UnorderedPlaceholders: Check for multiple unordered placeholders.
				$unordered_matches_count = preg_match_all( self::UNORDERED_SPRINTF_PLACEHOLDER_REGEX, $translation->getOriginal(), $unordered_matches );
				$unordered_matches       = $unordered_matches[0];

				if ( $unordered_matches_count >= 2 ) {
					WP_CLI::warning( sprintf(
						'Multiple placeholders should be ordered (%s)',
						$location
					) );
				}
			}
		}
	}

	/**
	 * Returns the metadata for a plugin or theme.
	 *
	 * @return array Meta data.
	 */
	protected function get_meta_data() {
		$file_data = $this->get_main_file_data();

		if ( isset( $file_data['Theme Name'] ) ) {
			$name         = $file_data['Theme Name'];
			$author       = $file_data['Author'];
			$bugs_address = sprintf( 'https://wordpress.org/support/theme/%s', $this->slug );
		} else {
			$name         = $file_data['Plugin Name'];
			$author       = $name;
			$bugs_address = sprintf( 'https://wordpress.org/support/plugin/%s', $this->slug );
		}

		$meta = [
			'name'               => $name,
			'version'            => $file_data['Version'],
			'comments'           => sprintf(
				"Copyright (C) %1\$s %2\$s\nThis file is distributed under the same license as the %3\$s package.",
				date( 'Y' ),
				$author,
				$name
			),
			'msgid-bugs-address' => $bugs_address,
		];

		if ( isset( $file_data['License'] ) ) {
			$meta['comments'] = sprintf(
				"Copyright (C) %1\$s %2\$s\nThis file is distributed under the %3\$s.",
				date( 'Y' ),
				$author,
				$file_data['License']
			);
		}

		return $meta;
	}

	/**
	 * Sets default POT file headers for the project.
	 */
	protected function set_default_headers() {
		$meta = $this->get_meta_data();

		$this->translations->setHeader( 'Project-Id-Version', $meta['name'] . ' ' . $meta['version'] );
		$this->translations->setHeader( 'Report-Msgid-Bugs-To', $meta['msgid-bugs-address'] );
		$this->translations->setHeader( 'Last-Translator', 'FULL NAME <EMAIL@ADDRESS>' );
		$this->translations->setHeader( 'Language-Team', 'LANGUAGE <LL@li.org>' );
		$this->translations->setHeader( 'X-Generator', 'WP-CLI ' . WP_CLI_VERSION );

		foreach( $this->headers as $key => $value ) {
			$this->translations->setHeader( $key, $value );
		}
	}

	/**
	 * Retrieves metadata from a file.
	 *
	 * Searches for metadata in the first 8kiB of a file, such as a plugin or theme.
	 * Each piece of metadata must be on its own line. Fields can not span multiple
	 * lines, the value will get cut at the end of the first line.
	 *
	 * If the file data is not within that first 8kiB, then the author should correct
	 * their plugin file and move the data headers to the top.
	 *
	 * @see get_file_data()
	 *
	 * @param string $file Path to the file.
	 * @param array $headers List of headers, in the format array('HeaderKey' => 'Header Name').
	 *
	 * @return array Array of file headers in `HeaderKey => Header Value` format.
	 */
	protected static function get_file_data( $file, $headers ) {
		// We don't need to write to the file, so just open for reading.
		$fp = fopen( $file, 'rb' );

		// Pull only the first 8kiB of the file in.
		$file_data = fread( $fp, 8192 );

		// PHP will close file handle, but we are good citizens.
		fclose( $fp );

		// Make sure we catch CR-only line endings.
		$file_data = str_replace( "\r", "\n", $file_data );

		return static::get_file_data_from_string( $file_data, $headers );
	}

	/**
	 * Retrieves metadata from a string.
	 *
	 * @param string $string String to look for metadata in.
	 * @param array $headers List of headers.
	 *
	 * @return array Array of file headers in `HeaderKey => Header Value` format.
	 */
	public static function get_file_data_from_string( $string, $headers  ) {
		foreach ( $headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $string, $match ) && $match[1] ) {
				$headers[ $field ] = static::_cleanup_header_comment( $match[1] );
			} else {
				$headers[ $field ] = '';
			}
		}

		return $headers;
	}

	/**
	 * Strip close comment and close php tags from file headers used by WP.
	 *
	 * @see _cleanup_header_comment()
	 *
	 * @param string $str Header comment to clean up.
	 *
	 * @return string
	 */
	protected static function _cleanup_header_comment( $str ) {
		return trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $str ) );
	}
}
