<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$autoload = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

WP_CLI::add_command( 'makepot plugin', '\WP_CLI\Makepot\PluginCommand' );
WP_CLI::add_command( 'makepot theme', '\WP_CLI\Makepot\ThemeCommand' );

