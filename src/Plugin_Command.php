<?php

namespace WP_CLI\Makepot;

use DirectoryIterator;
use IteratorIterator;
use WP_CLI;

class Plugin_Command extends Makepot_Command {
	protected $headers = [ 'Plugin Name', 'Plugin URI', 'Description', 'Author', 'Author URI', 'Version', 'Domain Path' ];
	/**
	 * Create a POT file for a WordPress plugin.
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
	 * : Text domain to look for in the source code. Defaults to the plugin slug.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create a POT file for the WordPress plugin in the current directory
	 *     $ wp makepot plugin . languages/my-plugin.pot
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
		$plugin_files = [];

		$files = new IteratorIterator( new DirectoryIterator( $this->source ) );

		/** @var DirectoryIterator $file */
		foreach ( $files as $file ) {
			if ( $file->isFile() && $file->isReadable() && 'php' === $file->getExtension()) {
				$plugin_files[] = $file->getRealPath();
			}
		}

		if ( empty( $plugin_files ) ) {
			WP_CLI::error( 'No plugin files found!' );
		}

		foreach ( $plugin_files as $plugin_file ) {
			$plugin_data = static::get_file_data( $plugin_file, array_combine( $this->headers, $this->headers ) );

			// Stop when we find a file with a plugin name header in it.
			if ( ! empty( $plugin_data['Plugin Name'] ) ) {
				$this->main_file      = "$this->source/$plugin_file";
				$this->main_file_data = $plugin_data;

				return;
			}
		}

		WP_CLI::error( 'No main plugin file found!' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_meta_data() {
		$file_data = $this->get_main_file_data();

		return [
			'name'               => $file_data['Plugin Name'],
			'version'            => $file_data['Version'],
			'comments'           => sprintf(
				"Copyright (C) %1\$s %2\$s\nThis file is distributed under the same license as the %3\$s package.",
				date( 'Y' ),
				$file_data['Plugin Name'],
				$file_data['Plugin Name']
			),
			'msgid-bugs-address' => sprintf( 'https://wordpress.org/support/plugin/%s', $this->slug ),
		];
	}
}
