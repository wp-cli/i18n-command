<?php

namespace WP_CLI\Makepot;

use WP_CLI;

class Theme_Command extends Makepot_Command {
	protected $headers = [ 'Theme Name', 'Theme URI', 'Description', 'Author', 'Author URI', 'Version', 'License', 'Domain Path' ];

	/**
	 * Create a POT file for a WordPress theme.
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
	 * : Theme slug. Defaults to the source directory's basename.
	 *
	 * [--domain=<domain>]
	 * : Text domain to look for in the source code. Defaults to the theme slug.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create a POT file for the WordPress theme in the current directory
	 *     $ wp makepot theme . languages/my-theme.pot
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		parent::__invoke( $args, $assoc_args );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function set_main_file() {
		$theme_data = static::get_file_data( "$this->source/style.css", array_combine( $this->headers, $this->headers ) );

		// Return file name when it contains a valid Theme Name header.
		if ( ! empty( $theme_data['Theme Name'] ) ) {
			$this->main_file      = "$this->source/style.css";
			$this->main_file_data = $theme_data;

			return;
		}

		WP_CLI::error( 'No valid theme stylesheet found!' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_meta_data() {
		$file_data = $this->get_main_file_data();

		$name = isset( $file_data['Theme Name'] ) ? $file_data['Theme Name'] : $this->slug;

		$meta = [
			'name'               => $name,
			'version'            => $file_data['Version'],
			'comments'           => sprintf(
				"Copyright (C) %1\$s %2\$s\nThis file is distributed under the same license as the %3\$s package.",
				date( 'Y' ),
				$file_data['Author'],
				$name
			),
			'msgid-bugs-address' => sprintf( 'https://wordpress.org/support/theme/%s', $this->slug ),
		];

		if ( isset( $file_data['License'] ) ) {
			$meta['comments'] = sprintf(
				"Copyright (C) %1\$s %2\$s\nThis file is distributed under the %3\$s.",
				date( 'Y' ),
				$file_data['Author'],
				$file_data['License']
			);
		}

		return $meta;
	}
}
