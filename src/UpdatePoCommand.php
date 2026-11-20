<?php

namespace WP_CLI\I18n;

use DirectoryIterator;
use Gettext\Generator\PoGenerator;
use Gettext\Headers;
use Gettext\Merge;
use Gettext\Translation;
use IteratorIterator;
use SplFileInfo;
use WP_CLI;
use WP_CLI\Utils;
use WP_CLI_Command;

class UpdatePoCommand extends WP_CLI_Command {
	/**
	 * Update PO files from a POT file.
	 *
	 * This behaves similarly to the [msgmerge](https://www.gnu.org/software/gettext/manual/html_node/msgmerge-Invocation.html) command.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : Path to an existing POT file to use for updating.
	 *
	 * [<destination>]
	 * : PO file to update or a directory containing multiple PO files.
	 *   Defaults to all PO files in the source directory.
	 *
	 * [--purge]
	 * : Remove obsolete strings and replace translator comments. Defaults to true.
	 *   By default, strings not found in the POT file are removed, and translator comments are replaced with those from the POT file.
	 *   Use `--no-purge` to preserve obsolete translations (marked with #~) and existing translator comments like copyright notices.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update all PO files from a POT file in the current directory.
	 *     $ wp i18n update-po example-plugin.pot
	 *     Success: Updated 3 files.
	 *
	 *     # Update a PO file from a POT file.
	 *     $ wp i18n update-po example-plugin.pot example-plugin-de_DE.po
	 *     Success: Updated 1 file.
	 *
	 *     # Update all PO files in a given directory from a POT file.
	 *     $ wp i18n update-po example-plugin.pot languages
	 *     Success: Updated 2 files.
	 *
	 *     # Update PO files while keeping obsolete strings and translator comments.
	 *     $ wp i18n update-po example-plugin.pot --no-purge
	 *     Success: Updated 3 files.
	 *
	 *     # Shows message when some files don't need updating.
	 *     $ wp i18n update-po example-plugin.pot languages
	 *     Success: Updated 2 files. 1 file unchanged.
	 *
	 * @when before_wp_load
	 *
	 * @param array<string>        $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 * @throws WP_CLI\ExitException
	 */
	public function __invoke( $args, $assoc_args ) {
		$source = realpath( $args[0] );
		if ( ! $source || ! is_file( $source ) ) {
			WP_CLI::error( 'Source file does not exist.' );
		}

		$destination = dirname( $source );

		if ( isset( $args[1] ) ) {
			$destination = $args[1];
		}

		if ( ! file_exists( $destination ) ) {
			WP_CLI::error( 'Destination file/folder does not exist.' );
		}

		if ( is_file( $destination ) ) {
			$files = [ new SplFileInfo( $destination ) ];
		} else {
			$files = new IteratorIterator( new DirectoryIterator( $destination ) );
		}

		$pot_translations = Translations::fromPoFiles( $source );

		/** @var array<int|string, bool|string> $assoc_args */
		$purge = (bool) Utils\get_flag_value( $assoc_args, 'purge', true );

		$updated_count   = 0;
		$unchanged_count = 0;
		/** @var DirectoryIterator $file */
		foreach ( $files as $file ) {
			if ( 'po' !== $file->getExtension() ) {
				continue;
			}

			if ( ! $file->isFile() || ! $file->isReadable() ) {
				WP_CLI::warning( sprintf( 'Could not read file %s', $file->getFilename() ) );
				continue;
			}

			$original_translations = Translations::fromPoFiles( $file->getPathname() );
			$po_translations       = $this->merge_translations( $original_translations, $pot_translations, $purge );

			// Check if the translations actually changed by comparing the objects.
			$has_changes = $this->translations_differ( $original_translations, $po_translations );

			// When using --no-purge, file-level comments being kept counts as a change.
			if ( ! $purge && null !== $original_translations->getDescription() ) {
				$has_changes = true;
			}

			// Update PO-Revision-Date to current date and time in UTC.
			// Uses gmdate() for consistency across different server timezones.
			$po_translations->getHeaders()->set( 'PO-Revision-Date', gmdate( 'Y-m-d\TH:i:sP' ) );

			// Reorder translations to match POT file order.
			$ordered_translations = $this->reorder_translations( $po_translations, $pot_translations );

			if ( ! ( new PoGenerator() )->generateFile( $ordered_translations, $file->getPathname() ) ) {
				WP_CLI::warning( sprintf( 'Could not update file %s', $file->getPathname() ) );
				continue;
			}

			if ( $has_changes ) {
				++$updated_count;
			} else {
				++$unchanged_count;
			}
		}

		// Build the success message.
		$message_parts   = array();
		$message_parts[] = sprintf( 'Updated %d %s', $updated_count, Utils\pluralize( 'file', $updated_count ) );
		if ( $unchanged_count > 0 ) {
			$message_parts[] = sprintf( '%d %s unchanged', $unchanged_count, Utils\pluralize( 'file', $unchanged_count ) );
		}

		WP_CLI::success( implode( '. ', $message_parts ) . '.' );
	}

	/**
	 * Merges the entries of a POT file into the translations of a PO file.
	 *
	 * @param Translations $po_translations  Translations of the PO file.
	 * @param Translations $pot_translations Translations of the POT file.
	 * @param bool         $purge            Whether to remove obsolete entries and replace translator comments.
	 * @return Translations The merged translations.
	 */
	private function merge_translations( Translations $po_translations, Translations $pot_translations, $purge ) {
		$merged = clone $po_translations;

		// The text domain is taken from the POT file if both files have one.
		$pot_domain = $pot_translations->getDomain();
		if ( ! empty( $merged->getDomain() ) && ! empty( $pot_domain ) ) {
			$merged->getHeaders()->set( Headers::HEADER_DOMAIN, $pot_domain );
		}

		$strategy = Merge::EXTRACTED_COMMENTS_THEIRS | Merge::REFERENCES_THEIRS;

		if ( $purge ) {
			$strategy |= Merge::COMMENTS_THEIRS;

			// File-level comments, like a copyright notice.
			$merged->setDescription( null );

			foreach ( $merged->getTranslations() as $po_entry ) {
				if ( ! $pot_translations->has( $po_entry ) ) {
					$merged->remove( $po_entry );
				}
			}
		} else {
			// Keep entries that are no longer in the POT file, but mark them as obsolete.
			foreach ( $merged->getTranslations() as $po_entry ) {
				if ( ! $pot_translations->has( $po_entry ) ) {
					$po_entry->disable();
				}
			}
		}

		foreach ( $pot_translations as $pot_entry ) {
			/** @var Translation $pot_entry */
			$merged->addOrMerge( clone $pot_entry, $strategy );
		}

		return $merged;
	}

	/**
	 * Check if two Translations objects differ.
	 *
	 * @param Translations $original Original translations.
	 * @param Translations $updated  Updated translations.
	 * @return bool True if translations differ, false otherwise.
	 */
	private function translations_differ( Translations $original, Translations $updated ) {
		// Quick check: if counts differ, they're different.
		if ( count( $original ) !== count( $updated ) ) {
			return true;
		}

		// Compare each translation entry.
		foreach ( $original as $translation ) {
			/** @var Translation $translation */
			$context      = $translation->getContext();
			$original_str = $translation->getOriginal();

			// Find the corresponding translation in the updated set.
			$updated_translation = $updated->find( $context, $original_str );

			// If translation doesn't exist in updated set, they differ.
			if ( ! $updated_translation ) {
				return true;
			}

			// Compare translation strings.
			if ( $translation->getTranslation() !== $updated_translation->getTranslation() ) {
				return true;
			}

			// Compare whether the entries are obsolete.
			if ( $translation->isDisabled() !== $updated_translation->isDisabled() ) {
				return true;
			}

			// Compare plural translations if they exist.
			$original_plurals = $translation->getPluralTranslations();
			$updated_plurals  = $updated_translation->getPluralTranslations();

			if ( $original_plurals !== $updated_plurals ) {
				return true;
			}

			// Compare references (source code locations).
			$original_refs = Translations::getReferenceList( $translation );
			$updated_refs  = Translations::getReferenceList( $updated_translation );

			$original_refs_sorted = $original_refs;
			$updated_refs_sorted  = $updated_refs;

			sort( $original_refs_sorted );
			sort( $updated_refs_sorted );

			if ( $original_refs_sorted !== $updated_refs_sorted ) {
				return true;
			}

			// Compare comments.
			if ( $translation->getExtractedComments()->toArray() !== $updated_translation->getExtractedComments()->toArray() ) {
				return true;
			}

			if ( $translation->getComments()->toArray() !== $updated_translation->getComments()->toArray() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Reorder translations to match the POT file order.
	 *
	 * @param Translations $po_translations  The merged PO translations.
	 * @param Translations $pot_translations The POT translations (source of truth for order).
	 *
	 * @return Translations Translations object with entries in POT file order.
	 */
	private function reorder_translations( Translations $po_translations, Translations $pot_translations ) {
		$ordered = Translations::create();

		// Copy headers, file-level comments and flags from the merged PO translations.
		foreach ( $po_translations->getHeaders()->toArray() as $name => $value ) {
			$ordered->getHeaders()->set( $name, $value );
		}

		$ordered->setDescription( $po_translations->getDescription() );
		$ordered->getFlags()->add( ...$po_translations->getFlags()->toArray() );

		// Add translations in POT file order.
		foreach ( $pot_translations as $pot_entry ) {
			/** @var Translation $pot_entry */
			$po_entry = $po_translations->find( $pot_entry->getContext(), $pot_entry->getOriginal() );
			if ( $po_entry ) {
				$ordered->add( clone $po_entry );
			}
		}

		// Add any remaining translations from PO that aren't in POT (e.g., obsolete/disabled translations).
		foreach ( $po_translations as $po_entry ) {
			/** @var Translation $po_entry */
			// Check if this entry is already in the ordered set.
			if ( ! $ordered->has( $po_entry ) ) {
				$ordered->add( clone $po_entry );
			}
		}

		return $ordered;
	}
}
