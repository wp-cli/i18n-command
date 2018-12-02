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

WP_CLI::add_command( 'i18n make-pot', '\WP_CLI\I18n\MakePotCommand' );

WP_CLI::add_command( 'i18n po2json', '\WP_CLI\I18n\Po2JsonCommand' );
