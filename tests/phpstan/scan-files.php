<?php
// phpcs:ignoreFile

namespace {

	define( 'WP_CLI_VERSION', '2.x.x' );

	class Requests_Response {
		/** @var bool */
		public $success;
		/** @var int */
		public $status_code;
		/** @var string */
		public $body;
	}
}
