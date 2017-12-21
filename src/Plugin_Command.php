<?php

namespace WP_CLI\Makepot;

use WP_CLI;

class Plugin_Command extends Makepot_Command {
	/**
	 * Create a POT file for a WordPress plugin.
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
	 * : Plugin slug. Defaults to the source directory's basename.
	 *
	 * [--domain=<domain>]
	 * : Text domain to look for in the source code. Defaults to the plugin slug.
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Create a POT file for the WordPress plugin in the current directory
	 *     $ wp makepot plugin . languages/my-plugin.pot
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		$this->meta = [
			'comments'           => "Copyright (C) {year} {package-name}\nThis file is distributed under the same license as the {package-name} package.",
			// Todo: Where should this be used?
			'description'        => 'Translation of the WordPress plugin {name} {version} by {author}',
			'msgid-bugs-address' => 'https://wordpress.org/support/plugin/{slug}',
			// Todo: Where should this be used?
			'copyright-holder'   => '{author}',
			'package-name'       => '{name}',
			'package-version'    => '{version}',
		];

		$this->headers = [ 'Plugin Name', 'Plugin URI', 'Description', 'Author', 'Author URI', 'Version' ];

		parent::__invoke( $args, $assoc_args );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function set_main_file() {
		$plugins_dir  = @opendir( $this->source );
		$plugin_files = [];
		if ( $plugins_dir ) {
			while ( ( $file = readdir( $plugins_dir ) ) !== false ) {
				if ( 0 === strpos( $file, '.' ) ) {
					continue;
				}

				if ( '.php' === substr( $file, - 4 ) ) {
					$plugin_files[] = $file;
				}
			}
			closedir( $plugins_dir );
		}

		if ( empty( $plugin_files ) ) {
			WP_CLI::error( 'No plugin files found!' );
		}

		foreach ( $plugin_files as $plugin_file ) {
			if ( ! is_readable( "$this->source/$plugin_file" ) ) {
				continue;
			}

			$plugin_data = static::get_file_data( "$this->source/$plugin_file", array_combine( $this->headers, $this->headers ) );

			// Stop when we find a file with a plugin name header in it.
			if ( ! empty( $plugin_data['Plugin Name'] ) ) {
				$this->main_file      = "$this->source/$plugin_file";
				$this->main_file_data = $plugin_data;

				return;
			}
		}

		WP_CLI::error( 'No main plugin file found!' );
	}
}
