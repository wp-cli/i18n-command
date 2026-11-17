<?php

namespace WP_CLI\I18n;

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
	 * Returns the translation with the given context and original string, creating it if needed.
	 *
	 * @param string|null $context  Context, if any.
	 * @param string      $original Original string.
	 * @param string|null $plural   Plural form of the original string, if any.
	 * @return Translation The existing or newly added translation.
	 */
	public function insert( ?string $context, string $original, ?string $plural = null ): Translation {
		$existing = $this->find( $context, $original );

		if ( $existing instanceof Translation ) {
			$translation = $existing;
		} else {
			$translation = Translation::create( $context, $original );

			// Keep the data of an entry that was added as a plain gettext translation.
			if ( null !== $existing ) {
				/** @var Translation $translation */
				$translation = $translation->mergeWith( $existing );
				$translation->disable( $existing->isDisabled() );
			}

			$this->add( $translation );
		}

		if ( null !== $plural && '' !== $plural && ! $translation->hasPlural() ) {
			$translation->setPlural( $plural );
		}

		return $translation;
	}
}
