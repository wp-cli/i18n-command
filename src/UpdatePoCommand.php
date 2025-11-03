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
	 * [--skip-purge]
	 * : Prevent removal of obsolete strings and preserve translator comments.
	 *   By default, strings not found in the POT file are removed, and translator comments are replaced with those from the POT file.
	 *   This flag keeps obsolete translations (marked with #~) and preserves existing translator comments like copyright notices.
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
	 *     $ wp i18n update-po example-plugin.pot --skip-purge
	 *     Success: Updated 3 files.
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
		
		$skip_purge = Utils\get_flag_value( $assoc_args, 'skip-purge', false );
		
		if ( ! $skip_purge ) {
			// By default, remove obsolete entries and replace translator comments
			$merge_flags |= Merge::REMOVE | Merge::COMMENTS_THEIRS;
		}

		$result_count = 0;
		/** @var DirectoryIterator $file */
		foreach ( $files as $file ) {
			if ( 'po' !== $file->getExtension() ) {
				continue;
			}

			if ( ! $file->isFile() || ! $file->isReadable() ) {
				WP_CLI::warning( sprintf( 'Could not read file %s', $file->getFilename() ) );
				continue;
			}

			// Preserve file-level comments when --skip-purge is set
			$file_comments = '';
			if ( $skip_purge ) {
				$file_comments = $this->extract_file_comments( $file->getPathname() );
			}

			$po_translations = Translations::fromPoFile( $file->getPathname() );
			$po_translations->mergeWith(
				$pot_translations,
				$merge_flags
			);

			if ( ! $po_translations->toPoFile( $file->getPathname() ) ) {
				WP_CLI::warning( sprintf( 'Could not update file %s', $file->getPathname() ) );
				continue;
			}

			// Restore file-level comments when --skip-purge is set
			if ( $skip_purge && ! empty( $file_comments ) ) {
				$this->restore_file_comments( $file->getPathname(), $file_comments );
			}

			++$result_count;
		}

		WP_CLI::success( sprintf( 'Updated %d %s.', $result_count, Utils\pluralize( 'file', $result_count ) ) );
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

		$lines           = explode( "\n", $content );
		$file_comments   = [];
		$found_msgid     = false;

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

		return false !== file_put_contents( $file_path, $updated_content );
	}
}
