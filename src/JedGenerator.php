<?php

namespace WP_CLI\I18n;

use Gettext\Generator\Generator;
use Gettext\Translation;
use Gettext\Translations;

/**
 * Jed file generator.
 *
 * Adds some more meta data to JED translation files than the default generator.
 */
class JedGenerator extends Generator {
	/**
	 * Options passed to json_encode().
	 *
	 * @var int JSON options.
	 */
	protected $json_options = 0;

	/**
	 * Source file.
	 *
	 * @var string Source file.
	 */
	protected $source = '';

	/**
	 * Constructor.
	 *
	 * @param int    $json_options Options passed to json_encode().
	 * @param string $source       Source file.
	 */
	public function __construct( int $json_options, string $source ) {
		$this->json_options = $json_options;
		$this->source       = $source;
	}

	public function generateString( Translations $translations ): string {
		$array = $this->generateArray( $translations );

		return json_encode( $array, $this->json_options );
	}

	public function generateArray( Translations $translations ): array {
		$plural_form = $translations->getHeaders()->getPluralForm();
		$plural_size = is_array( $plural_form ) ? ( $plural_form[0] - 1 ) : null;
		$messages    = [];

		foreach ( $translations as $translation ) {
			if ( ! $translation->getTranslation() || $translation->isDisabled() ) {
				continue;
			}

			$context  = $translation->getContext() ?: '';
			$original = $translation->getOriginal();

			if ( ! isset( $messages[ $context ] ) ) {
				$messages[ $context ] = [];
			}

			if ( self::hasPluralTranslations( $translation ) ) {
				$messages[ $context ][ $original ] = $translation->getPluralTranslations( $plural_size );
				array_unshift( $messages[ $context ][ $original ], $translation->getTranslation() );
			} else {
				$messages[ $context ][ $original ] = $translation->getTranslation();
			}
		}

		$configuration = [
			'' => [
				'domain'       => $translations->getDomain(),
				'lang'         => $translations->getLanguage() ?: 'en',
				'plural-forms' => $translations->getHeaders()->getPluralForm() ?: 'nplurals=2; plural=(n != 1);',
			],
		];

		return [
			'translation-revision-date' => $translations->getHeaders()->get( 'PO-Revision-Date' ),
			'generator'                 => 'WP-CLI/' . WP_CLI_VERSION,
			'source'                    => $this->source,
			'domain'                    => $translations->getDomain(),
			'locale_data'               => [
				$translations->getDomain() => $configuration + $messages,
			],
		];
	}

	private static function hasPluralTranslations( Translation $translation ): bool {
		return implode( '', $translation->getPluralTranslations() ) !== '';
	}
}
