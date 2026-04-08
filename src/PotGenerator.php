<?php

namespace WP_CLI\I18n;

use Gettext\Generators\Po as PoGenerator;
use Gettext\Translations;
use Gettext\Utils\ParsedComment;

/**
 * POT file generator.
 *
 * The only difference to the existing PO file generator is that this
 * adds some comments at the very beginning of the file.
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
	 * @param \Gettext\Translations $translations
	 * @param array<mixed>         $options
	 * @return string
	 */
	public static function toString( Translations $translations, array $options = [] ) {
		/** @var array<string> $lines */
		$lines   = static::$comments_before_headers;
		$lines[] = 'msgid ""';
		$lines[] = 'msgstr ""';

		$plural_form = $translations->getPluralForms();
		$plural_size = 1;
		if ( is_array( $plural_form ) && isset( $plural_form[0] ) && is_numeric( $plural_form[0] ) ) {
			$plural_size = (int) $plural_form[0] - 1;
		}

		foreach ( $translations->getHeaders() as $name => $value ) {
			$lines[] = sprintf( '"%s: %s\\n"', $name, $value );
		}

		$lines[] = '';

		foreach ( $translations as $translation ) {
			/** @var \Gettext\Translation $translation */
			if ( $translation->hasComments() ) {
				foreach ( $translation->getComments() as $comment ) {
					if ( is_scalar( $comment ) ) {
						$lines[] = '# ' . $comment;
					}
				}
			}

			if ( $translation->hasExtractedComments() ) {
				$unique_comments = array();

				/** @var ParsedComment|string $comment */
				foreach ( $translation->getExtractedComments() as $comment ) {
					$comment = ( $comment instanceof ParsedComment ? $comment->getComment() : $comment );
					if ( is_scalar( $comment ) ) {
						$comment_str = (string) $comment;
						if ( ! in_array( $comment_str, $unique_comments, true ) ) {
							$lines[]           = '#. ' . $comment_str;
							$unique_comments[] = $comment_str;
						}
					}
				}
			}

			foreach ( $translation->getReferences() as $reference ) {
				if ( is_array( $reference ) && isset( $reference[0] ) ) {
					$ref_str  = is_scalar( $reference[0] ) ? (string) $reference[0] : '';
					$line_str = '';
					if ( isset( $reference[1] ) && is_scalar( $reference[1] ) ) {
						$line_str = ':' . $reference[1];
					}
					$lines[] = '#: ' . $ref_str . $line_str;
				}
			}

			if ( $translation->hasFlags() ) {
				$lines[] = '#, ' . implode( ',', $translation->getFlags() );
			}

			$prefix = $translation->isDisabled() ? '#~ ' : '';

			if ( $translation->hasContext() ) {
				$lines[] = $prefix . 'msgctxt ' . self::convertString( $translation->getContext() );
			}

			self::addLines( $lines, $prefix . 'msgid', $translation->getOriginal() );

			if ( $translation->hasPlural() ) {
				self::addLines( $lines, $prefix . 'msgid_plural', $translation->getPlural() );

				for ( $i = 0; $i <= $plural_size; $i++ ) {
					self::addLines( $lines, $prefix . 'msgstr[' . $i . ']', '' );
				}
			} else {
				self::addLines( $lines, $prefix . 'msgstr', $translation->getTranslation() );
			}

			$lines[] = '';
		}

		$string_lines = array_filter( $lines, 'is_string' );
		return implode( "\n", $string_lines );
	}

	/**
	 * Escapes and adds double quotes to a string.
	 *
	 * @param string $text Multiline string.
	 *
	 * @return string[]
	 */
	protected static function wpMultilineQuote( $text ) {
		$lines = explode( "\n", $text );
		$last  = count( $lines ) - 1;

		foreach ( $lines as $k => $line ) {
			if ( $k === $last ) {
				$lines[ $k ] = self::convertString( $line );
			} else {
				$lines[ $k ] = self::convertString( $line . "\n" );
			}
		}

		return $lines;
	}

	/**
	 * Add one or more lines depending whether the string is multiline or not.
	 *
	 * @param array<mixed> &$lines Array lines should be added to.
	 * @param string        $name   Name of the line, e.g. msgstr or msgid_plural.
	 * @param string        $value  The line to add.
	 * @return void
	 */
	protected static function addLines( array &$lines, $name, $value ) {
		$newlines = self::wpMultilineQuote( $value );

		if ( count( $newlines ) === 1 ) {
			$lines[] = $name . ' ' . $newlines[0];
		} else {
			$lines[] = $name . ' ""';

			foreach ( $newlines as $line ) {
				$lines[] = $line;
			}
		}
	}
}
