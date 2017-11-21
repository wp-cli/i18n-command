<?php

namespace Swissspidy\WP_CLI_Makepot;

use WP_CLI;

class Makepot_Command extends \WP_CLI_Command {
	/**
	 * Create a POT file for a WordPress project.
	 *
	 * <directory>
	 * : Directory to scan for string extraction.
	 *
	 * <output>
	 * : Name of the resulting POT file.
	 *
	 * [--project=<type>]
	 * : Type of project this POT file is for.
	 * ---
	 * default: generic
	 * options:
	 *  - generic
	 *  - wp-frontend
	 *  - wp-admin
	 *  - wp-network-admin
	 *  - wp-tz
	 *  - wp-plugin
	 *  - wp-theme
	 *  - glotpress
	 *  - rosetta
	 *  - wporg-bb-forums
	 *  - wporg-themes
	 *  - wporg-plugins
	 *  - wporg-forums
	 *  - wordcamporg
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Create a POT file for the WordPress plugin in the current directory
	 *     $ wp makepot . languages/my-plugin.pot --project=wp-plugin
	 */
	public function __invoke( $args, $assoc_args ) {
		$project = \WP_CLI\Utils\get_flag_value( $assoc_args, 'project', true );

		require_once __DIR__ . '../i18n-tools/makepot.php';

		$makepot = new \MakePOT();

		$result = $makepot->{str_replace( '-', '_', $project )}( $args[0], $args[1] );

		if ( false === $result ) {
			WP_CLI::error( 'Could not generate a POT file!' );
		}

		WP_CLI::success( 'Success: POT file successfully generated!' );
	}
}
