<?php

namespace WP_CLI\Makepot;

use Gettext\Translation;
use Gettext\Translations;
use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils;

class Theme_Command extends Makepot_Command {
	/**
	 * Create a POT file for a WordPress theme.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : Directory to scan for string extraction.
	 *
	 * <output>
	 * : Name of the resulting POT file.
	 *
	 * [--slug=<slug>]
	 * : Theme slug. Defaults to the source directory's basename.
	 *
	 * [--domain=<domain>]
	 * : Text domain to look for in the source code. Defaults to the theme slug.
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Create a POT file for the WordPress theme in the current directory
	 *     $ wp makepot theme . languages/my-theme.pot
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		$this->meta = [
			'description'        => 'Translation of the WordPress theme {name} {version} by {author}',
			'msgid-bugs-address' => 'https://wordpress.org/support/theme/{slug}',
			'copyright-holder'   => '{author}',
			'package-name'       => '{name}',
			'package-version'    => '{version}',
			'comments'           => 'Copyright (C) {year} {author}\nThis file is distributed under the same license as the {package-name} package.',
		];

		$this->headers = [ 'Theme Name', 'Theme URI', 'Description', 'Author', 'Author URI', 'Version', 'License' ];

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
}
