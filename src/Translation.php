<?php

namespace WP_CLI\I18n;

use Gettext\Merge;
use Gettext\Translation as GettextTranslation;

/**
 * A single translation entry.
 *
 * Keeps flags and references in the order they were added.
 */
class Translation extends GettextTranslation {

	protected function __construct( string $id ) {
		parent::__construct( $id );

		$this->references = new OrderedReferences();
		$this->flags      = new OrderedFlags();
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return static
	 */
	public static function create( ?string $context, string $original ): GettextTranslation {
		/** @var static $translation */
		$translation = parent::create( $context, $original );

		return $translation;
	}

	public function hasContext(): bool {
		return null !== $this->context && '' !== $this->context;
	}

	public function hasPlural(): bool {
		return null !== $this->plural && '' !== $this->plural;
	}

	public function hasTranslation(): bool {
		return null !== $this->translation && '' !== $this->translation;
	}

	/**
	 * @param bool $check_content Whether to check that at least one plural translation is not empty.
	 */
	public function hasPluralTranslations( bool $check_content = false ): bool {
		if ( $check_content ) {
			return '' !== implode( '', $this->getPluralTranslations() );
		}

		return ! empty( $this->getPluralTranslations() );
	}

	/**
	 * Returns the references in the order they were added.
	 *
	 * @return array<int, array{0: string, 1: int|null}> List of file name and line number pairs.
	 */
	public function getReferenceEntries(): array {
		return OrderedReferences::entries( $this->references );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Unlike the parent method, this keeps translations and plurals that are "0",
	 * and does not change whether the translation is disabled.
	 */
	public function mergeWith( GettextTranslation $translation, int $strategy = 0 ): GettextTranslation {
		/** @var Translation $merged */
		$merged   = parent::mergeWith( $translation, $strategy );
		$override = (bool) ( $strategy & Merge::TRANSLATIONS_OVERRIDE );

		$their_translation = $translation->getTranslation();
		$their_plural      = $translation->getPlural();

		$merged->translation = $this->translation;
		if ( ! $this->hasTranslation() || ( $override && null !== $their_translation && '' !== $their_translation ) ) {
			$merged->translation = $their_translation;
		}

		$merged->plural = $this->plural;
		if ( ! $this->hasPlural() || ( $override && null !== $their_plural && '' !== $their_plural ) ) {
			$merged->plural = $their_plural;
		}

		$merged->disabled = $this->disabled;

		return $merged;
	}
}
