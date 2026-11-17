<?php

namespace WP_CLI\I18n;

use Gettext\Flags;

/**
 * Flags that keep the order in which they were added, instead of being sorted alphabetically.
 */
class OrderedFlags extends Flags {

	public function add( string ...$flags ): Flags {
		foreach ( $flags as $flag ) {
			if ( ! $this->has( $flag ) ) {
				$this->flags[] = $flag;
			}
		}

		return $this;
	}
}
