<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$autoload = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

if ( class_exists( 'WP_CLI\Dispatcher\CommandNamespace' ) ) {
	WP_CLI::add_command( 'i18n', '\WP_CLI\I18n\CommandNamespace' );
}

WP_CLI::add_command( 'i18n make-pot', '\WP_CLI\I18n\MakePotCommand', array(
	'before_invoke' => function() {
		$min_version = '5.4';
		if ( version_compare( PHP_VERSION, $min_version, '<' ) ) {
			WP_CLI::error( "The `wp i18n make-pot` command requires PHP {$min_version} or newer." );
		}
	}
) );
