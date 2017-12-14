<?php

namespace WP_CLI\Makepot;

use Gettext\Generators\Po;
use Gettext\Translations;

/**
 * POT file generator.
 *
 * The only difference to the existing PO file generator is that this
 * adds some comments at the very beginning of the file.
 */
class Pot_Generator extends Po {
	protected static $comments_before_headers = [];

	/**
	 * Text to include as a comment before the start of the PO contents
	 *
	 * Doesn't need to include # in the beginning of lines, these are added automatically.
	 *
	 * @param array $comment
	 */
	public static function setCommentBeforeHeaders( $comment ) {
		$comments = explode( "\n", $comment );

		foreach( $comments as $line ) {
			static::$comments_before_headers[] = '# ' . $line;
		}
	}

	/**
	 * {@parentDoc}.
	 */
	public static function toString( Translations $translations, array $options = [] ) {
		return implode( "\n", static::$comments_before_headers ) . "\n" . parent::toString( $translations, $options );
	}
}
