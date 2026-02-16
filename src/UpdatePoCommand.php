<?php

namespace WP_CLI\I18n;

use DirectoryIterator;
use Gettext\Extractors\Po;
use Gettext\Merge;
use Gettext\Translations;
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

		$pot_translations = Translations::fromPoFile( $source );

		// Build merge flags based on options
		$merge_flags = Merge::ADD | Merge::EXTRACTED_COMMENTS_THEIRS | Merge::REFERENCES_THEIRS | Merge::DOMAIN_OVERRIDE;

		$purge = Utils\get_flag_value( $assoc_args, 'purge', true );

		if ( $purge ) {
			// By default, remove obsolete entries and replace translator comments
			$merge_flags |= Merge::REMOVE | Merge::COMMENTS_THEIRS;
		}

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

			// Preserve file-level comments when --no-purge is set
			$file_comments = '';
			if ( ! $purge ) {
				$file_comments = $this->extract_file_comments( $file->getPathname() );
			}
			$po_translations       = Translations::fromPoFile( $file->getPathname() );
			$original_translations = clone $po_translations;

			$po_translations->mergeWith(
				$pot_translations,
				$merge_flags
			);

			// Check if the translations actually changed by comparing the objects.
			$has_changes = $this->translations_differ( $original_translations, $po_translations );

			// When using --no-purge, file-level comments being restored counts as a change.
			if ( ! $purge && ! empty( $file_comments ) ) {
				$has_changes = true;
			}

			// Update PO-Revision-Date to current date and time in UTC.
			// Uses gmdate() for consistency across different server timezones.
			$po_translations->setHeader( 'PO-Revision-Date', gmdate( 'Y-m-d\TH:i:sP' ) );

			// Reorder translations to match POT file order.
			$ordered_translations = $this->reorder_translations( $po_translations, $pot_translations );

			if ( ! $ordered_translations->toPoFile( $file->getPathname() ) ) {
				WP_CLI::warning( sprintf( 'Could not update file %s', $file->getPathname() ) );
				continue;
			}

			// Restore file-level comments when --no-purge is set
			if ( ! $purge && ! empty( $file_comments ) ) {
				if ( ! $this->restore_file_comments( $file->getPathname(), $file_comments ) ) {
					WP_CLI::warning( sprintf( 'Could not restore file-level comments for %s', $file->getPathname() ) );
				}
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
	 * Extract file-level comments from a PO file.
	 *
	 * These are comments that appear before the first msgid in the file.
	 *
	 * @param string $file_path Path to the PO file.
	 * @return string The file-level comments.
	 */
	private function extract_file_comments( $file_path ) {
		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return '';
		}

		$lines         = explode( "\n", $content );
		$file_comments = [];
		$found_msgid   = false;

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			// Stop when we hit the first msgid
			if ( preg_match( '/^msgid\s/', $trimmed ) ) {
				$found_msgid = true;
				break;
			}

			// Collect comment lines
			if ( preg_match( '/^#([^.,:~]|$)/', $trimmed ) ) {
				$file_comments[] = $line;
			}
		}

		return $found_msgid && ! empty( $file_comments ) ? implode( "\n", $file_comments ) . "\n" : '';
	}

	/**
	 * Restore file-level comments to a PO file.
	 *
	 * @param string $file_path Path to the PO file.
	 * @param string $comments The file-level comments to restore.
	 * @return bool True on success, false on failure.
	 */
	private function restore_file_comments( $file_path, $comments ) {
		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return false;
		}

		// Prepend the comments to the file content
		$updated_content = $comments . $content;

		// Use atomic file operation with temporary file
		$temp_file = $file_path . '.tmp';
		if ( false === file_put_contents( $temp_file, $updated_content ) ) {
			return false;
		}

		// Rename is atomic on most filesystems
		if ( ! rename( $temp_file, $file_path ) ) {
			// Clean up temp file on failure
			@unlink( $temp_file );
			return false;
		}

		return true;
	}

	/**
	 * Check if two Translations objects differ .
	 *
	 * @param Translations $original Original translations.
	 * @param Translations $updated  Updated translations.
	 * @return bool true if translations differ, false otherwise.
	 */
	private function translations_differ( Translations $original, Translations $updated ) {
		// Quick check: if counts differ, they're different.
		if ( count( $original ) !== count( $updated ) ) {
			return true;
		}

		// Compare each translation entry.
		foreach ( $original as $translation ) {
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

			// Compare plural translations if they exist.
			$original_plurals = $translation->getPluralTranslations();
			$updated_plurals  = $updated_translation->getPluralTranslations();

			if ( $original_plurals !== $updated_plurals ) {
				return true;
			}

			// Compare references (source code locations).
			$original_refs = $translation->getReferences();
			$updated_refs  = $updated_translation->getReferences();

			$original_refs_sorted = $original_refs;
			$updated_refs_sorted  = $updated_refs;

			sort( $original_refs_sorted );
			sort( $updated_refs_sorted );

			if ( $original_refs_sorted !== $updated_refs_sorted ) {
				return true;
			}

			// Compare comments.
			if ( $translation->getExtractedComments() !== $updated_translation->getExtractedComments() ) {
				return true;
			}

			if ( $translation->getComments() !== $updated_translation->getComments() ) {
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
		$ordered = new Translations();

		// Copy headers from the merged PO translations.
		foreach ( $po_translations->getHeaders() as $name => $value ) {
			$ordered->setHeader( $name, $value );
		}

		// Add translations in POT file order.
		foreach ( $pot_translations as $pot_entry ) {
			$po_entry = $po_translations->find( $pot_entry );
			if ( $po_entry ) {
				$ordered[] = $po_entry->getClone();
			}
		}

		// Add any remaining translations from PO that aren't in POT (e.g., obsolete/disabled translations).
		foreach ( $po_translations as $po_entry ) {
			// Check if this entry is already in the ordered set.
			if ( ! $ordered->find( $po_entry ) ) {
				$ordered[] = $po_entry->getClone();
			}
		}

		return $ordered;
	}
}
