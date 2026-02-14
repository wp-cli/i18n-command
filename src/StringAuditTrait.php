<?php

namespace WP_CLI\I18n;

use Gettext\Translations;
use WP_CLI;

trait StringAuditTrait {
	/**
	 * Audits translation strings for potential issues with placeholders.
	 *
	 * Performs validation checks on strings including:
	 * - Flags strings with placeholders that lack translator comments
	 * - Flags strings with inconsistent translator comments
	 * - Flags empty strings with no translatable content
	 * - Flags multiple unordered placeholders
	 * - Flags missing singular placeholders in plural translations
	 * - Flags mismatched placeholders between singular/plural forms
	 *
	 * @param Translations $translations The translations to audit.
	 * @param array        $file_headers Optional. File headers to filter out from comments. Default empty array.
	 */
	protected function perform_string_audit( Translations $translations, array $file_headers = [] ) {
		// Regular expression to match sprintf placeholders.
		$sprintf_placeholder_regex = '/(?:
			(?<!%)                     # Don\'t match a literal % (%%).
			(
				%                          # Start of placeholder.
				(?:[0-9]+\$)?              # Optional ordering of the placeholders.
				[+-]?                      # Optional sign specifier.
				(?:
					(?:0|\'.)?                 # Optional padding specifier - excluding the space.
					-?                         # Optional alignment specifier.
					[0-9]*                     # Optional width specifier.
					(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
					|                      # Only recognize the space as padding in combination with a width specifier.
					(?:[ ])?                   # Optional space padding specifier.
					-?                         # Optional alignment specifier.
					[0-9]+                     # Width specifier.
					(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
				)
				[bcdeEfFgGosuxX]           # Type specifier.
			)
		)/x';

		// "Unordered" means there's no position specifier: '%s', not '%2$s'.
		$unordered_sprintf_placeholder_regex = '/(?:
			(?<!%)                     # Don\'t match a literal % (%%).
			%                          # Start of placeholder.
			[+-]?                      # Optional sign specifier.
			(?:
				(?:0|\'.)?                 # Optional padding specifier - excluding the space.
				-?                         # Optional alignment specifier.
				[0-9]*                     # Optional width specifier.
				(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
				|                      # Only recognize the space as padding in combination with a width specifier.
				(?:[ ])?                   # Optional space padding specifier.
				-?                         # Optional alignment specifier.
				[0-9]+                     # Width specifier.
				(?:\.(?:[ 0]|\'.)?[0-9]+)? # Optional precision specifier with optional padding character.
			)
			[bcdeEfFgGosuxX]           # Type specifier.
		)/x';

		foreach ( $translations as $translation ) {
			/** @var \Gettext\Translation $translation */

			$references = $translation->getReferences();

			// File headers don't have any file references.
			$location = $translation->hasReferences() ? '(' . implode( ':', $references[0] ) . ')' : '';

			// Check 1: Flag strings with placeholders that should have translator comments.
			if (
				! $translation->hasExtractedComments() &&
				preg_match( $sprintf_placeholder_regex, $translation->getOriginal(), $placeholders ) >= 1
			) {
				$message = sprintf(
					'The string "%1$s" contains placeholders but has no "translators:" comment to clarify their meaning. %2$s',
					$translation->getOriginal(),
					$location
				);
				WP_CLI::warning( $message );
			}

			// Check 2: Flag strings with different translator comments.
			if ( $translation->hasExtractedComments() ) {
				$comments = $translation->getExtractedComments();

				// Remove plugin/theme header information from comments.
				$comments = array_filter(
					$comments,
					function ( $comment ) use ( $file_headers ) {
						/** @var \Gettext\Comments\ParsedComment|string $comment */
						/** @var string $file_header */
						foreach ( $file_headers as $file_header ) {
							if ( 0 === strpos( ( $comment instanceof \Gettext\Comments\ParsedComment ? $comment->getComment() : $comment ), $file_header ) ) {
								return null;
							}
						}

						return $comment;
					}
				);

				$unique_comments = array();

				// Remove duplicate comments.
				$comments = array_filter(
					$comments,
					function ( $comment ) use ( &$unique_comments ) {
						/** @var \Gettext\Comments\ParsedComment|string $comment */
						if ( in_array( ( $comment instanceof \Gettext\Comments\ParsedComment ? $comment->getComment() : $comment ), $unique_comments, true ) ) {
							return null;
						}

						$unique_comments[] = ( $comment instanceof \Gettext\Comments\ParsedComment ? $comment->getComment() : $comment );

						return $comment;
					}
				);

				$comments_count = count( $comments );

				if ( $comments_count > 1 ) {
					$message = sprintf(
						"The string \"%1\$s\" has %2\$d different translator comments. %3\$s\n%4\$s",
						$translation->getOriginal(),
						$comments_count,
						$location,
						implode( "\n", $unique_comments )
					);
					WP_CLI::warning( $message );
				}
			}

			$non_placeholder_content = trim( preg_replace( '`^([\'"])(.*)\1$`Ds', '$2', $translation->getOriginal() ) );
			$non_placeholder_content = preg_replace( $sprintf_placeholder_regex, '', $non_placeholder_content );

			// Check 3: Flag empty strings without any translatable content.
			if ( '' === $non_placeholder_content ) {
				$message = sprintf(
					'Found string without translatable content. %s',
					$location
				);
				WP_CLI::warning( $message );
			}

			// Check 4: Flag strings with multiple unordered placeholders (%s %s %s vs. %1$s %2$s %3$s).
			$unordered_matches_count = preg_match_all( $unordered_sprintf_placeholder_regex, $translation->getOriginal(), $unordered_matches );
			$unordered_matches       = $unordered_matches[0];

			if ( $unordered_matches_count >= 2 ) {
				$message = sprintf(
					'Multiple placeholders should be ordered. %s',
					$location
				);
				WP_CLI::warning( $message );
			}

			if ( $translation->hasPlural() ) {
				preg_match_all( $sprintf_placeholder_regex, $translation->getOriginal(), $single_placeholders );
				$single_placeholders = $single_placeholders[0];

				preg_match_all( $sprintf_placeholder_regex, $translation->getPlural(), $plural_placeholders );
				$plural_placeholders = $plural_placeholders[0];

				// see https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#plurals
				if ( count( $single_placeholders ) < count( $plural_placeholders ) ) {
					// Check 5: Flag things like _n( 'One comment', '%s Comments' )
					$message = sprintf(
						'Missing singular placeholder, needed for some languages. See https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#plurals %s',
						$location
					);
					WP_CLI::warning( $message );
				} else {
					// Reordering is fine, but mismatched placeholders is probably wrong.
					sort( $single_placeholders );
					sort( $plural_placeholders );

					// Check 6: Flag things like _n( '%s Comment (%d)', '%s Comments (%s)' )
					if ( $single_placeholders !== $plural_placeholders ) {
						$message = sprintf(
							'Mismatched placeholders for singular and plural string. %s',
							$location
						);
						WP_CLI::warning( $message );
					}
				}
			}
		}
	}
}
