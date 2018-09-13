<?php

namespace WP_CLI\I18n;

use Gettext\Generators\Po;
use Gettext\Translations;

/**
 * POT file generator.
 *
 * The only difference to the existing PO file generator is that this
 * adds some comments at the very beginning of the file.
 */
class PotGenerator extends Po {
	protected static $comments_before_headers = [];

	/**
	 * Text to include as a comment before the start of the PO contents
	 *
	 * Doesn't need to include # in the beginning of lines, these are added automatically.
	 *
	 * @param string $comment File comment.
	 */
	public static function setCommentBeforeHeaders( $comment ) {
		$comments = explode( "\n", $comment );

		foreach( $comments as $line ) {
			if ( '' !== trim( $line ) ) {
				static::$comments_before_headers[] = '# ' . $line;
			}
		}
	}

	/**
	 * {@parentDoc}.
	 */
	public static function toString( Translations $translations, array $options = [] ) {
		$result = '';

		if ( ! empty( static::$comments_before_headers ) ) {
			$result = implode( "\n", static::$comments_before_headers ) . "\n";
		}

		return $result . parent::toString( $translations, $options );
	}
}
