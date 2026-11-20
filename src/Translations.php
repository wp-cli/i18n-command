<?php

namespace WP_CLI\I18n;

use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Gettext\Translations as GettextTranslations;

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
	 */
	public static function fromPoFiles( $files ): GettextTranslations {
		$translations = null;

		foreach ( (array) $files as $file ) {
			$loaded = static::fromPoString( (string) file_get_contents( $file ) );

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

		foreach ( preg_split( '/\R\s*\R/', $content ) ?: [] as $block ) {
			foreach ( $loader->loadString( $block, static::create() ) as $translation ) {
				$translations->addOrMerge( $translation );
			}
		}

		return $translations;
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
