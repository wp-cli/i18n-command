<?php

namespace WP_CLI\Makepot;

use Gettext\Translation;
use Gettext\Translations;
use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils;

abstract class Makepot_Command extends WP_CLI_Command {
	/** @var  Translations */
	protected $translations;

	protected $source;
	protected $dest;
	protected $slug;

	protected $headers = [];
	protected $meta = [];
	protected $main_file = '';
	protected $main_file_data = [];

	public function __invoke( $args, $assoc_args ) {
		$this->source = $args[0];
		$this->dest   = $args[1];

		$this->slug = Utils\get_flag_value( $assoc_args, 'slug', Utils\basename( $this->source ) );

		$this->set_main_file();

		if ( ! $this->makepot() ) {
			WP_CLI::error( 'Could not generate a POT file!' );
		}

		WP_CLI::success( 'Success: POT file successfully generated!' );
	}

	abstract protected function set_main_file();

	protected function get_main_file() {
		return $this->main_file;
	}

	protected function get_main_file_data() {
		return $this->main_file_data;
	}

	protected function makepot() {
		$this->translations = new Translations();

		$meta = $this->get_meta_data();
		Pot_Generator::setCommentBeforeHeaders( $meta['comments'] );

		$this->set_default_headers();

		// POT files have no Language header.
		$this->translations->deleteHeader( Translations::HEADER_LANGUAGE );

		// Todo: Allow users to circumvent this? Needs changes in WordPressFunctionsScanner.
		$this->translations->setDomain( $this->slug );

		$file_data = $this->get_main_file_data();

		WordPress_Code_Extractor::fromDirectory( $this->source, $this->translations, [
			'wpExtractTemplates' => isset( $file_data['Theme Name'] )
		] );

		// Set entries from main file data.
		unset( $file_data['Version'], $file_data['License'] );

		foreach ( $file_data as $header => $data ) {
			$translation = new Translation( '', $data );
			$translation->addExtractedComment( sprintf( '%s of the plugin/theme', $header ) );

			$this->translations[] = $translation;
		}

		return Pot_Generator::toFile( $this->translations, $this->dest );
	}

	protected function get_meta_data() {
		$file_data = $this->get_main_file_data();
		$meta      = $this->meta;

		if ( isset( $file_data['Theme Name'] ) ) {
			if ( isset( $file_data['License'] ) ) {
				$meta['comments'] = "Copyright (C) {year} {author}\nThis file is distributed under the {$file_data['License']}.";
			} else {
				$meta['comments'] = "Copyright (C) {year} {author}\nThis file is distributed under the same license as the {package-name} package.";
			}
		}

		$placeholders            = [];
		$placeholders['year']    = date( 'Y' );
		$placeholders['version'] = $file_data['Version'];
		$placeholders['author']  = $file_data['Author'];
		$placeholders['name']    = isset( $file_data['Plugin Name'] ) ? $file_data['Plugin Name'] : $file_data['Theme Name'];
		$placeholders['slug']    = $this->slug;

		$placeholders = array_merge( $meta, $placeholders );

		$placeholder_values = array_values( $placeholders );
		$placeholder_keys   = array_map( function ( $x ) {
			return "{{$x}}";
		}, array_keys( $placeholders ) );

		foreach ( $meta as $key => $value ) {
			$meta[ $key ] = str_replace( $placeholder_keys, $placeholder_values, $value );
		}

		return $meta;
	}

	/**
	 * Sets default POT file headers for the project.
	 */
	protected function set_default_headers() {
		$meta = $this->get_meta_data();

		$this->translations->setHeader( 'Project-Id-Version', $meta['package-name'] . ' ' . $meta['package-version'] );
		$this->translations->setHeader( 'Report-Msgid-Bugs-To', $meta['msgid-bugs-address'] );
		$this->translations->setHeader( 'Last-Translator', 'FULL NAME <EMAIL@ADDRESS>' );
		$this->translations->setHeader( 'Language-Team', 'LANGUAGE <LL@li.org>' );
	}

	/**
	 * Retrieve metadata from a file.
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
		$fp = fopen( $file, 'r' );

		// Pull only the first 8kiB of the file in.
		$file_data = fread( $fp, 8192 );

		// PHP will close file handle, but we are good citizens.
		fclose( $fp );

		// Make sure we catch CR-only line endings.
		$file_data = str_replace( "\r", "\n", $file_data );

		return static::get_file_data_from_string( $file_data, $headers );
	}

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
	 * @since 2.8.0
	 * @access private
	 *
	 * @see https://core.trac.wordpress.org/ticket/8497
	 *
	 * @param string $str Header comment to clean up.
	 *
	 * @return string
	 */
	protected static function _cleanup_header_comment( $str ) {
		return trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $str ) );
	}
}
