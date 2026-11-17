<?php

namespace WP_CLI\I18n;

use Gettext\Translation;
use Gettext\Translations;

/**
 * POT file generator.
 *
 * The only difference to the PO file generator is that this adds some
 * comments at the very beginning of the file and leaves plural forms untranslated.
 */
class PotGenerator extends PoGenerator {
	/**
	 * @var array<string>
	 */
	protected static $comments_before_headers = [];

	/**
	 * Text to include as a comment before the start of the PO contents
	 *
	 * Doesn't need to include # in the beginning of lines, these are added automatically.
	 *
	 * @param string $comment File comment.
	 * @return void
	 */
	public static function setCommentBeforeHeaders( $comment ) {
		$comments = explode( "\n", $comment );

		foreach ( $comments as $line ) {
			if ( '' !== trim( $line ) ) {
				static::$comments_before_headers[] = '# ' . $line;
			}
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param Translations $translations
	 * @param array<mixed> $options
	 * @return string
	 */
	public static function toString( Translations $translations, array $options = [] ) {
		$plural_form = $translations->getHeaders()->getPluralForm();
		$plural_size = is_array( $plural_form ) ? ( $plural_form[0] - 1 ) : 1;

		$lines = array_merge( static::$comments_before_headers, static::getHeaderLines( $translations ) );

		foreach ( $translations as $translation ) {
			/** @var Translation $translation */

			$prefix = static::addMetaLines( $lines, $translation );

			if ( null !== $translation->getPlural() && '' !== $translation->getPlural() ) {
				static::addLines( $lines, $prefix . 'msgid_plural', $translation->getPlural() );

				for ( $i = 0; $i <= $plural_size; $i++ ) {
					static::addLines( $lines, $prefix . 'msgstr[' . $i . ']', '' );
				}
			} else {
				static::addLines( $lines, $prefix . 'msgstr', (string) $translation->getTranslation() );
			}

			$lines[] = '';
		}

		return implode( "\n", $lines );
	}
}
