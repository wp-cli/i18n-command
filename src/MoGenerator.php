<?php

namespace WP_CLI\I18n;

use Gettext\Translation;
use Gettext\Translations;

/**
 * MO file generator.
 *
 * Unlike the generator shipped with gettext/gettext, this keeps translations that are "0".
 */
class MoGenerator extends Generator {

	/**
	 * {@inheritdoc}
	 *
	 * @param Translations $translations
	 * @param array<mixed> $options
	 * @return string
	 */
	public static function toString( Translations $translations, array $options = [] ) {
		$headers = '';

		foreach ( $translations->getHeaders()->toArray() as $name => $value ) {
			$headers .= sprintf( "%s: %s\n", $name, $value );
		}

		/** @var array<string|Translation> $messages */
		$messages = [ '' => $headers ];

		foreach ( $translations as $translation ) {
			/** @var Translation $translation */

			if ( null === $translation->getTranslation() || '' === $translation->getTranslation() || $translation->isDisabled() ) {
				continue;
			}

			$original_string = $translation->getOriginal();

			if ( null !== $translation->getContext() && '' !== $translation->getContext() ) {
				$original_string = $translation->getContext() . "\x04" . $original_string;
			}

			$messages[ $original_string ] = $translation;
		}

		ksort( $messages );

		$num_entries        = count( $messages );
		$originals_table    = '';
		$translations_table = '';
		$originals_index    = [];
		$translations_index = [];

		$plural_form = $translations->getHeaders()->getPluralForm();
		$plural_size = is_array( $plural_form ) ? ( (int) $plural_form[0] - 1 ) : null;

		foreach ( $messages as $original_string => $translation ) {
			$original_string = (string) $original_string;

			if ( is_string( $translation ) ) {
				// Headers.
				$translation_string = $translation;
			} else {
				$translation_string = (string) $translation->getTranslation();

				if (
					null !== $translation->getPlural() &&
					'' !== $translation->getPlural() &&
					'' !== implode( '', $translation->getPluralTranslations() )
				) {
					$original_string    .= "\x00" . $translation->getPlural();
					$translation_string .= "\x00" . implode( "\x00", $translation->getPluralTranslations( $plural_size ) );
				}
			}

			$originals_index[] = [
				'relativeOffset' => strlen( $originals_table ),
				'length'         => strlen( $original_string ),
			];

			$originals_table .= $original_string . "\x00";

			$translations_index[] = [
				'relativeOffset' => strlen( $translations_table ),
				'length'         => strlen( $translation_string ),
			];

			$translations_table .= $translation_string . "\x00";
		}

		// Offset of table with the original strings index: right after the header (which is 7 words).
		$originals_index_offset = 7 * 4;

		// Size of table with the original strings index.
		$originals_index_size = $num_entries * ( 4 + 4 );

		// Offset of table with the translation strings index: right after the original strings index table.
		$translations_index_offset = $originals_index_offset + $originals_index_size;

		// Size of table with the translation strings index.
		$translations_index_size = $num_entries * ( 4 + 4 );

		// Hashing table starts after the header and after the index table.
		$originals_strings_offset = $translations_index_offset + $translations_index_size;

		// Translations start after the keys.
		$translations_strings_offset = $originals_strings_offset + strlen( $originals_table );

		// Magic number.
		$mo = pack( 'L', 0x950412de );

		// File format revision.
		$mo .= pack( 'L', 0 );

		// Number of strings.
		$mo .= pack( 'L', $num_entries );

		// Offset of table with original strings.
		$mo .= pack( 'L', $originals_index_offset );

		// Offset of table with translation strings.
		$mo .= pack( 'L', $translations_index_offset );

		// Size of hashing table: we don't use it.
		$mo .= pack( 'L', 0 );

		// Offset of hashing table: it would start right after the translations index table.
		$mo .= pack( 'L', $translations_index_offset + $translations_index_size );

		// Write the lengths & offsets of the original strings.
		foreach ( $originals_index as $info ) {
			$mo .= pack( 'L', $info['length'] );
			$mo .= pack( 'L', $originals_strings_offset + $info['relativeOffset'] );
		}

		// Write the lengths & offsets of the translated strings.
		foreach ( $translations_index as $info ) {
			$mo .= pack( 'L', $info['length'] );
			$mo .= pack( 'L', $translations_strings_offset + $info['relativeOffset'] );
		}

		// Write original strings.
		$mo .= $originals_table;

		// Write translation strings.
		$mo .= $translations_table;

		return $mo;
	}
}
