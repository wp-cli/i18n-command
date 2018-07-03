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
	protected $exclude = [ 'node_modules', '.git', '.svn', '.CVS', '.hg', 'vendor' ];

	/**
	 * @var string
	 */
	protected $slug;

	/**
	 * @var array
	 */
	protected $main_file_data = [];

	/**
	 * Create a POT file for a WordPress plugin or theme.
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
	 * : Plugin slug. Defaults to the source directory's basename.
	 *
	 * [--domain=<domain>]
	 * : Text domain to look for in the source code. Defaults to the plugin/theme slug.
	 *
	 * [--merge[=<file>]]
	 * : Existing POT file file whose content should be merged with the extracted strings.
	 * If left empty, defaults to the destination POT file.
	 * [--exclude=<paths>]
	 * : Include additional ignored paths as CSV (e.g. 'tests,bin,.github').
	 *
	 * By default, the following files and folders are ignored: node_modules, .git, .svn, .CVS, .hg, vendor.
	 *
	 * Leading and trailing slashes are ignored, i.e. `/my/directory/` is the same as `my/directory`.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create a POT file for the WordPress plugin/theme in the current directory
	 *     $ wp i18n make-pot . languages/my-plugin.pot
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		$this->source = realpath( $args[0] );
		$this->slug   = Utils\get_flag_value( $assoc_args, 'slug', Utils\basename( $this->source ) );

		if ( ! $this->source || ! is_dir( $this->source ) ) {
			WP_CLI::error( 'Not a valid source directory!' );
		}

		$this->retrieve_main_file_data();

		// Determine destination.
		if ( isset( $args[1] ) ) {
			$this->destination = $args[1];
		} else {
			$file_data = $this->get_main_file_data();

			// Current directory.
			$this->destination = $this->slug . '.pot';

			if ( isset( $file_data['Domain Path'] ) ) {
				// Domain Path inside source folder.
				$this->destination = $this->source . DIRECTORY_SEPARATOR . $file_data['Domain Path'] . DIRECTORY_SEPARATOR . $this->slug . '.pot';
			}
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

		if ( isset( $assoc_args['exclude'] ) ) {
			$this->exclude = array_filter( array_merge( $this->exclude, explode( ',', $assoc_args['exclude'] ) ) );
			$this->exclude = array_map(function($exclude) {
				return ltrim( rtrim( $exclude, '/\\' ), '/\\' );
			}, $this->exclude);
			$this->exclude = array_unique( $this->exclude );
		}

		if ( ! $this->makepot( Utils\get_flag_value( $assoc_args, 'domain', $this->slug ) ) ) {
			WP_CLI::error( 'Could not generate a POT file!' );
		}

		WP_CLI::success( 'POT file successfully generated!' );
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
	 * @param string $domain The text domain to extract.
	 *
	 * @return bool True on success, false otherwise.
	 */
	protected function makepot( $domain ) {
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

		$this->translations->setDomain( $domain );

		$file_data = $this->get_main_file_data();

		// Extract 'Template Name' headers in theme files.
		WordPressCodeExtractor::fromDirectory( $this->source, $this->translations, [
			'wpExtractTemplates' => isset( $file_data['Theme Name'] ),
			'exclude'            => $this->exclude,
		] );

		unset( $file_data['Version'], $file_data['License'], $file_data['Domain Path'] );

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

		foreach( $this->translations as $translation ) {
			if ( ! $translation->hasExtractedComments() ) {
				continue;
			}

			$comments = $translation->getExtractedComments();
			$comments_count = count( $comments );

			if ( $comments_count > 1 ) {
				WP_CLI::warning( sprintf(
					'The string "%1$s" has %2$d different translator comments.',
					$translation->getOriginal(),
					$comments_count
				) );
			}
		}

		return PotGenerator::toFile( $this->translations, $this->destination );
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
