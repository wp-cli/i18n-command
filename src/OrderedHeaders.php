<?php

namespace WP_CLI\I18n;

use Gettext\Headers;

/**
 * Headers that keep the order in which they were set.
 *
 * The default implementation sorts headers alphabetically, which would
 * reorder the headers of every file that is read and written again.
 */
class OrderedHeaders extends Headers {

	/**
	 * @param array<string, string> $headers
	 */
	public function __construct( array $headers = [] ) {
		parent::__construct();

		$this->headers = $headers;
	}

	public function set( string $name, string $value ): Headers {
		$this->headers[ trim( $name ) ] = trim( $value );

		return $this;
	}

	public function mergeWith( Headers $headers ): Headers {
		$merged          = clone $this;
		$merged->headers = $headers->toArray() + $merged->headers;

		return $merged;
	}
}
