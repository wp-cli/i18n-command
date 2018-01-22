<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$autoload = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

WP_CLI::add_command( 'makepot', '\WP_CLI\Makepot\Makepot_Command', array(
	'before_invoke' => function() {
		$min_version = '5.4';
		if ( version_compare( PHP_VERSION, $min_version, '<' ) ) {
			WP_CLI::error( "The `wp makepot` command requires PHP {$min_version} or newer." );
		}
	}
) );
