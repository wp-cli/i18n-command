<?php

namespace WP_CLI\I18n;

use Gettext\Translation;
use Gettext\Translations;

/**
 * POT file generator.
 *
 * Adds a comment at the very beginning of the file and leaves plural forms untranslated.
 */
class PotGenerator extends Generator {
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
		$plural_size = is_array( $plural_form ) ? ( (int) $plural_form[0] - 1 ) : 1;

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

	/**
	 * Returns the lines for the header entry.
	 *
	 * @param Translations $translations Translations instance.
	 * @return array<string>
	 */
	protected static function getHeaderLines( Translations $translations ) {
		$lines = [ 'msgid ""', 'msgstr ""' ];

		foreach ( $translations->getHeaders()->toArray() as $name => $value ) {
			$lines[] = sprintf( '"%s: %s\\n"', $name, $value );
		}

		$lines[] = '';

		return $lines;
	}

	/**
	 * Adds the comments, references, flags, context and original string of an entry.
	 *
	 * @param array<string> &$lines      Array lines should be added to.
	 * @param Translation   $translation Translation entry.
	 * @return string Prefix to use for the remaining lines of the entry.
	 */
	protected static function addMetaLines( array &$lines, Translation $translation ) {
		foreach ( $translation->getComments()->toArray() as $comment ) {
			$lines[] = '# ' . $comment;
		}

		foreach ( $translation->getExtractedComments()->toArray() as $comment ) {
			$lines[] = '#. ' . $comment;
		}

		foreach ( \WP_CLI\I18n\Translations::getReferenceList( $translation ) as $reference ) {
			$lines[] = '#: ' . $reference[0] . ( null !== $reference[1] ? ':' . $reference[1] : '' );
		}

		if ( count( $translation->getFlags() ) > 0 ) {
			$lines[] = '#, ' . implode( ',', $translation->getFlags()->toArray() );
		}

		$prefix = $translation->isDisabled() ? '#~ ' : '';

		if ( null !== $translation->getContext() && '' !== $translation->getContext() ) {
			$lines[] = $prefix . 'msgctxt ' . static::convertString( $translation->getContext() );
		}

		static::addLines( $lines, $prefix . 'msgid', $translation->getOriginal() );

		return $prefix;
	}

	/**
	 * Escapes and adds double quotes to a string.
	 *
	 * @param string $text Multiline string.
	 *
	 * @return string[]
	 */
	protected static function multilineQuote( $text ) {
		$lines = explode( "\n", $text );
		$last  = count( $lines ) - 1;

		foreach ( $lines as $k => $line ) {
			if ( $k === $last ) {
				$lines[ $k ] = static::convertString( $line );
			} else {
				$lines[ $k ] = static::convertString( $line . "\n" );
			}
		}

		return $lines;
	}

	/**
	 * Add one or more lines depending whether the string is multiline or not.
	 *
	 * @param array<string> &$lines Array lines should be added to.
	 * @param string        $name   Name of the line, e.g. msgstr or msgid_plural.
	 * @param string        $value  The line to add.
	 * @return void
	 */
	protected static function addLines( array &$lines, $name, $value ) {
		$newlines = static::multilineQuote( $value );

		if ( count( $newlines ) === 1 ) {
			$lines[] = $name . ' ' . $newlines[0];
		} else {
			$lines[] = $name . ' ""';

			foreach ( $newlines as $line ) {
				$lines[] = $line;
			}
		}
	}

	/**
	 * Converts a string to its PO representation.
	 *
	 * @param string $value String to convert.
	 * @return string
	 */
	public static function convertString( $value ) {
		return '"' . strtr(
			$value,
			[
				"\x00" => '',
				'\\'   => '\\\\',
				"\t"   => '\t',
				"\r"   => '\r',
				"\n"   => '\n',
				'"'    => '\\"',
			]
		) . '"';
	}
}
