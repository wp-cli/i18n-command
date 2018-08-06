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
	 * @var string
	 */
	protected $source;

	/**
	 * @var string
	 */
	protected $destination;

	/**
	 * @var array
	 */
	protected $merge = [];

	/**
	 * @var Translations
	 */
	protected $exceptions;

	/**
	 * @var array
	 */
	protected $include = [];

	/**
	 * @var array
	 */
	protected $exclude = [ 'node_modules', '.git', '.svn', '.CVS', '.hg', 'vendor', 'Gruntfile.js', 'webpack.config.js', '*.min.js' ];

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
	 * @var string
	 */
	protected $copyright_holder;

	/**
	 * @var string
	 */
	protected $package_name;

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
	 * [--merge[=<files>]]
	 * : One or more existing POT files whose contents should be merged with the extracted strings.
	 * If left empty, defaults to the destination POT file.
	 *
	 * [--except=<files>]
	 * : If set, only strings not already existing in one of the passed POT files will be extracted.
	 *
	 * [--include=<paths>]
	 * : Only take specific files and folders into account for the string extraction.
	 * Leading and trailing slashes are ignored, i.e. `/my/directory/` is the same as `my/directory`.
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
	 * [--copyright-holder=<name>]
	 * : Name to use for the copyright comment in the resulting POT file.
	 *
	 * [--package-name=<name>]
	 * : Name to use for package name in the resulting POT file. Overrides anything found in a plugin or theme.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create a POT file for the WordPress plugin/theme in the current directory
	 *     $ wp i18n make-pot . languages/my-plugin.pot
	 *
	 * @when before_wp_load
	 *
	 * @throws WP_CLI\ExitException
	 */
	public function __invoke( $args, $assoc_args ) {
		$this->handle_arguments( $args, $assoc_args );

		$translations = $this->extract_strings();

		if ( ! $translations ) {
			WP_CLI::warning( 'No strings found' );
		}

		$translations_count = count( $translations );

		if ( 1 === $translations_count ) {
			WP_CLI::debug( sprintf( 'Extracted %d string', $translations_count ), 'make-pot' );
		} else {
			WP_CLI::debug( sprintf( 'Extracted %d strings', $translations_count ), 'make-pot' );
		}

		if ( ! PotGenerator::toFile( $translations, $this->destination ) ) {
			WP_CLI::error( 'Could not generate a POT file!' );
		}

		WP_CLI::success( 'POT file successfully generated!' );
	}

	/**
	 * Process arguments from command-line in a reusable way.
	 *
	 * @throws WP_CLI\ExitException
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function handle_arguments( $args, $assoc_args ) {
		$array_arguments = array( 'headers' );
		$assoc_args      = Utils\parse_shell_arrays( $assoc_args, $array_arguments );

		$this->source           = realpath( $args[0] );
		$this->slug             = Utils\get_flag_value( $assoc_args, 'slug', Utils\basename( $this->source ) );
		$this->skip_js          = Utils\get_flag_value( $assoc_args, 'skip-js', $this->skip_js );
		$this->headers          = Utils\get_flag_value( $assoc_args, 'headers', $this->headers );
		$this->package_name     = Utils\get_flag_value( $assoc_args, 'package-name', 'Unknown' );
		$this->copyright_holder = Utils\get_flag_value( $assoc_args, 'copyright-holder', 'Unknown' );

		$ignore_domain = Utils\get_flag_value( $assoc_args, 'ignore-domain', false );

		if ( ! $this->source || ! is_dir( $this->source ) ) {
			WP_CLI::error( 'Not a valid source directory!' );
		}

		$this->main_file_data = $this->get_main_file_data();

		if ( $ignore_domain ) {
			WP_CLI::debug( 'Extracting all strings regardless of text domain', 'make-pot' );
		}

		if ( ! $ignore_domain ) {
			$this->domain = $this->slug;

			if ( ! empty( $this->main_file_data['Text Domain'] ) ) {
				$this->domain = $this->main_file_data['Text Domain'];
			}

			$this->domain = Utils\get_flag_value( $assoc_args, 'domain', $this->domain );

			WP_CLI::debug( sprintf( 'Extracting all strings with text domain "%s"', $this->domain ), 'make-pot' );
		}

		// Determine destination.
		$this->destination = "{$this->source}/{$this->slug}.pot";

		if ( ! empty( $this->main_file_data['Domain Path'] ) ) {
			// Domain Path inside source folder.
			$this->destination = sprintf(
				'%s/%s/%s.pot',
				$this->source,
				$this->unslashit( $this->main_file_data['Domain Path'] ),
				$this->slug
			);
		}

		if ( isset( $args[1] ) ) {
			$this->destination = $args[1];
		}

		WP_CLI::debug( sprintf( 'Destination: %s', $this->destination ), 'make-pot' );

		// Two is_dir() checks in case of a race condition.
		if ( ! is_dir( dirname( $this->destination ) ) &&
		     ! mkdir( dirname( $this->destination ), 0777, true ) &&
		     ! is_dir( dirname( $this->destination ) )
		) {
			WP_CLI::error( 'Could not create destination directory!' );
		}

		if ( isset( $assoc_args['merge'] ) ) {
			if ( true === $assoc_args['merge'] ) {
				$this->merge = [ $this->destination ];
			} elseif ( ! empty( $assoc_args['merge'] ) ) {
				$this->merge = explode( ',', $assoc_args['merge'] );
			}

			$this->merge = array_filter(
				$this->merge,
				function ( $file ) {
					if ( ! file_exists( $file ) ) {
						WP_CLI::warning( sprintf( 'Invalid file provided to --merge: %s', $file ) );

						return false;
					}

					return true;
				}
			);

			if ( ! empty( $this->merge ) ) {
				WP_CLI::debug(
					sprintf(
						'Merging with existing POT %s: %s',
						WP_CLI\Utils\pluralize( 'file', count( $this->merge ) ),
						implode( ',', $this->merge )
					),
					'make-pot'
				);
			}
		}

		$this->exceptions = new Translations();

		if ( isset( $assoc_args['except'] ) ) {
			$exceptions = explode( ',', $assoc_args['except'] );

			$exceptions = array_filter(
				$exceptions,
				function ( $exception ) {
					if ( ! file_exists( $exception ) ) {
						WP_CLI::warning( sprintf( 'Invalid file provided to --except: %s', $exception ) );

						return false;
					}

					$exception_translations = new Translations();

					Po::fromFile( $exception, $exception_translations );
					$this->exceptions->mergeWith( $exception_translations );

					return true;
				} );

			if ( ! empty( $exceptions ) ) {
				WP_CLI::debug( sprintf( 'Ignoring any string already existing in: %s', implode( ',', $exceptions ) ), 'make-pot' );
			}
		}

		if ( isset( $assoc_args['include'] ) ) {
			$this->include = array_filter( explode( ',', $assoc_args['include'] ) );
			$this->include = array_map( [ $this, 'unslashit' ], $this->include );
			$this->include = array_unique( $this->include );

			WP_CLI::debug( sprintf( 'Only including the following files: %s', implode( ',', $this->include ) ), 'make-pot' );
		}

		if ( isset( $assoc_args['exclude'] ) ) {
			$this->exclude = array_filter( array_merge( $this->exclude, explode( ',', $assoc_args['exclude'] ) ) );
			$this->exclude = array_map( [ $this, 'unslashit' ], $this->exclude );
			$this->exclude = array_unique( $this->exclude );
		}

		WP_CLI::debug( sprintf( 'Excluding the following files: %s', implode( ',', $this->exclude ) ), 'make-pot' );
	}

	/**
	 * Removes leading and trailing slashes of a string.
	 *
	 * @param string $string What to add and remove slashes from.
	 * @return string String without leading and trailing slashes.
	 */
	protected function unslashit( $string ) {
		return ltrim( rtrim( trim( $string ), '/\\' ), '/\\' );
	}

	/**
	 * Retrieves the main file data of the plugin or theme.
	 *
	 * @throws WP_CLI\ExitException
	 *
	 * @return array
	 */
	protected function get_main_file_data() {
		$stylesheet = sprintf( '%s/style.css', $this->source );

		if ( is_file( $stylesheet ) && is_readable( $stylesheet ) ) {
			$theme_data = static::get_file_data( $stylesheet, array_combine( $this->get_file_headers( 'theme' ), $this->get_file_headers( 'theme' ) ) );

			// Stop when it contains a valid Theme Name header.
			if ( ! empty( $theme_data['Theme Name'] ) ) {
				WP_CLI::log( 'Theme stylesheet detected.' );
				WP_CLI::debug( sprintf( 'Theme stylesheet: %s', $stylesheet ), 'make-pot' );

				return $theme_data;
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

				return $plugin_data;
			}
		}

		WP_CLI::debug( 'No valid theme stylesheet or plugin file found, treating as a regular project.', 'make-pot' );

		return [];
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
	 * Creates a POT file and stores it on disk.
	 *
	 * @throws WP_CLI\ExitException
	 *
	 * @return Translations A Translation set.
	 */
	protected function extract_strings() {
		$translations = new Translations();

		// Add existing strings first but don't keep headers.
		if ( ! empty( $this->merge ) ) {
			$existing_translations = new Translations();
			Po::fromFile( $this->merge, $existing_translations );
			$translations->mergeWith( $existing_translations, Merge::ADD | Merge::REMOVE );
		}

		PotGenerator::setCommentBeforeHeaders( $this->get_file_comment() );

		$this->set_default_headers( $translations );

		// POT files have no Language header.
		$translations->deleteHeader( Translations::HEADER_LANGUAGE );

		if ( $this->domain ) {
			$translations->setDomain( $this->domain );
		}

		unset( $this->main_file_data['Version'], $this->main_file_data['License'], $this->main_file_data['Domain Path'], $this->main_file_data['Text Domain'] );

		// Set entries from main file data.
		foreach ( $this->main_file_data as $header => $data ) {
			if ( empty( $data ) ) {
				continue;
			}

			$translation = new Translation( '', $data );

			if ( isset( $this->main_file_data['Theme Name'] ) ) {
				$translation->addExtractedComment( sprintf( '%s of the theme', $header ) );
			} else {
				$translation->addExtractedComment( sprintf( '%s of the plugin', $header ) );
			}

			$translations[] = $translation;
		}

		try {
			PhpCodeExtractor::fromDirectory( $this->source, $translations, [
				// Extract 'Template Name' headers in theme files.
				'wpExtractTemplates' => isset( $this->main_file_data['Theme Name'] ),
				'include'            => $this->include,
				'exclude'            => $this->exclude,
				'extensions'         => [ 'php' ],
			] );

			if ( ! $this->skip_js ) {
				JsCodeExtractor::fromDirectory(
					$this->source,
					$translations,
					[
						'include'    => $this->include,
						'exclude'    => $this->exclude,
						'extensions' => [ 'js' ],
					]
				);
			}
		} catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		foreach( $this->exceptions as $translation ) {
			if ( $translations->find( $translation ) ) {
				unset( $translations[ $translation->getId() ] );
			}
		}

		$this->audit_strings( $translations );

		return $translations;
	}

	/**
	 * Audits strings.
	 *
	 * Goes through all extracted strings to find possible mistakes.
	 *
	 * @param Translations $translations Translations object.
	 */
	protected function audit_strings( $translations ) {
		foreach( $translations as $translation ) {
			/** @var Translation $translation */

			$references = $translation->getReferences();

			// File headers don't have any file references.
			$location = $translation->hasReferences() ? '(' . implode( ':', array_shift( $references ) ) . ')' : '';

			// Check 1: Flag strings with placeholders that should have translator comments.
			if (
				! $translation->hasExtractedComments() &&
				preg_match( self::SPRINTF_PLACEHOLDER_REGEX, $translation->getOriginal(), $placeholders ) >= 1
			) {
				WP_CLI::warning( sprintf(
					'The string "%1$s" contains placeholders but has no "translators:" comment to clarify their meaning. %2$s',
					$translation->getOriginal(),
					$location
				) );
			}

			// Check 2: Flag strings with different translator comments.
			if ( $translation->hasExtractedComments() ) {
				$comments = $translation->getExtractedComments();
				$comments_count = count( $comments );

				if ( $comments_count > 1 ) {
					WP_CLI::warning( sprintf(
						'The string "%1$s" has %2$d different translator comments. %3$s',
						$translation->getOriginal(),
						$comments_count,
						$location
					) );
				}
			}

			$non_placeholder_content = trim( preg_replace( '`^([\'"])(.*)\1$`Ds', '$2', $translation->getOriginal() ) );
			$non_placeholder_content = preg_replace( self::SPRINTF_PLACEHOLDER_REGEX, '', $non_placeholder_content );

			// Check 3: Flag empty strings without any translatable content.
			if ( '' === $non_placeholder_content ) {
				WP_CLI::warning( sprintf(
					'Found string without translatable content. %s',
					$location
				) );
			}

			// Check 4: Flag strings with multiple unordered placeholders (%s %s %s vs. %1$s %2$s %3$s).
			$unordered_matches_count = preg_match_all( self::UNORDERED_SPRINTF_PLACEHOLDER_REGEX, $translation->getOriginal(), $unordered_matches );
			$unordered_matches = $unordered_matches[0];

			if ( $unordered_matches_count >= 2 ) {
				WP_CLI::warning( sprintf(
					'Multiple placeholders should be ordered. %s',
					$location
				) );
			}

			if ( $translation->hasPlural() ) {
				preg_match_all( self::SPRINTF_PLACEHOLDER_REGEX, $translation->getOriginal(), $single_placeholders );
				$single_placeholders = $single_placeholders[0];

				preg_match_all( self::SPRINTF_PLACEHOLDER_REGEX, $translation->getPlural(), $plural_placeholders );
				$plural_placeholders = $plural_placeholders[0];

				// see https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#plurals
				if ( count( $single_placeholders ) < count( $plural_placeholders ) ) {
					// Check 5: Flag things like _n( 'One comment', '%s Comments' )
					WP_CLI::warning( sprintf(
						'Missing singular placeholder, needed for some languages. See https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#plurals %s',
						$location
					) );
				} else {
					// Reordering is fine, but mismatched placeholders is probably wrong.
					sort( $single_placeholders );
					sort( $plural_placeholders );

					// Check 6: Flag things like _n( '%s Comment (%d)', '%s Comments (%s)' )
					if ( $single_placeholders !== $plural_placeholders ) {
						WP_CLI::warning( sprintf(
							'Mismatched placeholders for singular and plural string. %s',
							$location
						) );
					}
				}
			}
		}

	}

	/**
	 * Returns the copyright comment for the given package.
	 *
	 * @return array Meta data.
	 */
	protected function get_file_comment() {
		$author = $this->copyright_holder;
		$name   = $this->package_name;

		if ( isset( $this->main_file_data['Theme Name'] ) ) {
			$name   = $this->main_file_data['Theme Name'];
			$author = $this->main_file_data['Author'];
		} elseif ( isset( $this->main_file_data['Plugin Name'] ) ) {
			$name   = $this->main_file_data['Plugin Name'];
			$author = $name;
		}

		$author = null === $author ? $this->copyright_holder : $author;
		$name   = null === $name ? $this->package_name : $name;

		if ( isset( $this->main_file_data['License'] ) ) {
			return sprintf(
				"Copyright (C) %1\$s %2\$s\nThis file is distributed under the %3\$s.",
				date( 'Y' ),
				$author,
				$this->main_file_data['License']
			);
		}

		return sprintf(
			"Copyright (C) %1\$s %2\$s\nThis file is distributed under the same license as the %3\$s package.",
			date( 'Y' ),
			$author,
			$name
		);
	}

	/**
	 * Sets default POT file headers for the project.
	 *
	 * @param Translations $translations Translations object.
	 */
	protected function set_default_headers( $translations ) {
		$name         = $this->package_name;
		$version      = $this->get_wp_version();
		$bugs_address = null;

		if ( ! $version && isset( $this->main_file_data['Version'] ) ) {
			$version = $this->main_file_data['Version'];
		}

		if ( isset( $this->main_file_data['Theme Name'] ) ) {
			$name         = $this->main_file_data['Theme Name'];
			$bugs_address = sprintf( 'https://wordpress.org/support/theme/%s', $this->slug );
		} elseif ( isset( $this->main_file_data['Plugin Name'] ) ) {
			$name         = $this->main_file_data['Plugin Name'];
			$bugs_address = sprintf( 'https://wordpress.org/support/plugin/%s', $this->slug );
		}

		$name = null === $name ? $this->package_name : $name;

		$translations->setHeader( 'Project-Id-Version', $name . ( $version ? ' ' . $version : '' ) );

		if ( null !== $bugs_address ) {
			$translations->setHeader( 'Report-Msgid-Bugs-To', $bugs_address );
		}

		$translations->setHeader( 'Last-Translator', 'FULL NAME <EMAIL@ADDRESS>' );
		$translations->setHeader( 'Language-Team', 'LANGUAGE <LL@li.org>' );
		$translations->setHeader( 'X-Generator', 'WP-CLI ' . WP_CLI_VERSION );

		foreach ( $this->headers as $key => $value ) {
			$translations->setHeader( $key, $value );
		}
	}

	/**
	 * Extracts the WordPress version number from wp-includes/version.php.
	 *
	 * @return string|false Version number on success, false otherwise.
	 */
	private function get_wp_version() {
		$version_php = $this->source . '/wp-includes/version.php';
		if ( ! file_exists( $version_php) || ! is_readable( $version_php ) ) {
			return false;
		}

		return preg_match( '/\$wp_version\s*=\s*\'(.*?)\';/', file_get_contents( $version_php ), $matches ) ? $matches[1] : false;
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
