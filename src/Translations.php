<?php

namespace WP_CLI\I18n;

use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Gettext\Translations as GettextTranslations;
use RuntimeException;

/**
 * A set of translations.
 *
 * Comes with a set of default headers.
 */
class Translations extends GettextTranslations {

	protected function __construct() {
		parent::__construct();

		$defaults = [
			'Project-Id-Version'        => '',
			'Report-Msgid-Bugs-To'      => '',
			'Last-Translator'           => '',
			'Language-Team'             => '',
			'MIME-Version'              => '1.0',
			'Content-Type'              => 'text/plain; charset=UTF-8',
			'Content-Transfer-Encoding' => '8bit',
			'POT-Creation-Date'         => date( 'c' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			'PO-Revision-Date'          => date( 'c' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			'Language'                  => '',
		];

		foreach ( $defaults as $name => $value ) {
			$this->headers->set( $name, $value );
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return static
	 */
	public static function create( ?string $domain = null, ?string $language = null ): GettextTranslations {
		/** @var static $translations */
		$translations = parent::create( $domain, $language );

		return $translations;
	}

	/**
	 * Loads one or more PO or POT files into a single set of translations.
	 *
	 * Entries that appear in more than one file are merged.
	 *
	 * @param string|array<string> $files A file path or a list of file paths.
	 * @return static
	 *
	 * @throws RuntimeException If a file cannot be read.
	 */
	public static function fromPoFiles( $files ): GettextTranslations {
		$translations = null;

		foreach ( (array) $files as $file ) {
			$content = is_file( $file ) && is_readable( $file ) ? file_get_contents( $file ) : false;

			if ( false === $content ) {
				throw new RuntimeException( sprintf( 'Could not read file %s', $file ) );
			}

			$loaded = static::fromPoString( $content );

			/** @var static $translations */
			$translations = null === $translations ? $loaded : $translations->mergeWith( $loaded );
		}

		return $translations ?? static::create();
	}

	/**
	 * Loads translations from the contents of a PO or POT file.
	 *
	 * Unlike the loader of gettext/gettext, this merges entries that appear more than once
	 * instead of keeping only the last one.
	 *
	 * @param string $content File contents.
	 * @return static
	 */
	public static function fromPoString( string $content ): GettextTranslations {
		$loader = new PoLoader();

		/** @var static $translations */
		$translations = $loader->loadString( $content, static::create() );

		// One msgid line belongs to the header entry.
		$entries = (int) preg_match_all( '/^(#~\s*)?msgid\s/m', $content ) - 1;

		if ( $entries <= count( $translations ) ) {
			return $translations;
		}

		foreach ( $translations->getTranslations() as $translation ) {
			$translations->remove( $translation );
		}

		foreach ( self::splitPoEntries( $content ) as $block ) {
			foreach ( $loader->loadString( $block, static::create() )->getTranslations() as $translation ) {
				$translations->addOrMerge( $translation );
			}
		}

		return $translations;
	}

	/**
	 * Splits the contents of a PO file into its entries.
	 *
	 * Like the PO loader, an entry ends at an empty line, or at a `#` line once its `msgid` has been read.
	 *
	 * @param string $content File contents.
	 * @return array<string> The entries.
	 */
	private static function splitPoEntries( string $content ): array {
		$entries = [];
		$entry   = [];
		$has_id  = false;

		foreach ( preg_split( '/\R/', $content ) ?: [] as $line ) {
			$trimmed = trim( $line );

			if ( '' === $trimmed || ( '#' === $trimmed && $has_id ) ) {
				$entries[] = implode( "\n", $entry );
				$entry     = [];
				$has_id    = false;
				continue;
			}

			$entry[] = $line;
			$has_id  = $has_id || 1 === preg_match( '/^(#~\s*)?msgid\s/', $trimmed );
		}

		$entries[] = implode( "\n", $entry );

		return array_filter( $entries );
	}

	/**
	 * Returns the translation with the given context and original string, creating it if needed.
	 *
	 * @param string|null $context  Context, if any.
	 * @param string      $original Original string.
	 * @param string|null $plural   Plural form of the original string, if any.
	 * @return Translation The existing or newly added translation.
	 */
	public function insert( ?string $context, string $original, ?string $plural = null ): Translation {
		$translation = $this->find( $context, $original );

		if ( null === $translation ) {
			$translation = Translation::create( $context, $original );
			$this->add( $translation );
		}

		if ( null !== $plural && '' !== $plural && ! self::hasValue( $translation->getPlural() ) ) {
			$translation->setPlural( $plural );
		}

		return $translation;
	}

	/**
	 * Returns the references of a translation as a flat list.
	 *
	 * @param Translation $translation Translation entry.
	 * @return array<int, array{0: string, 1: int|null}> List of file name and line number pairs.
	 */
	public static function getReferenceList( Translation $translation ): array {
		$entries = [];

		foreach ( $translation->getReferences()->toArray() as $filename => $lines ) {
			if ( empty( $lines ) ) {
				$entries[] = [ (string) $filename, null ];
				continue;
			}

			foreach ( $lines as $line ) {
				$entries[] = [ (string) $filename, $line ];
			}
		}

		return $entries;
	}

	/**
	 * Checks whether a string is set and not empty. Unlike a truthiness check, "0" is a valid value.
	 *
	 * @param string|null $value Value to check.
	 * @return bool
	 */
	public static function hasValue( ?string $value ): bool {
		return null !== $value && '' !== $value;
	}
}
