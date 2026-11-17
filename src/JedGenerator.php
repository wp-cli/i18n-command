<?php

namespace WP_CLI\I18n;

use Gettext\Translation;
use Gettext\Translations;

/**
 * Jed file generator.
 *
 * Adds some more meta data to JED translation files than the default generator.
 */
class JedGenerator extends Generator {
	/**
	 * @var array<string, mixed>
	 */
	public static $options = [
		'json'   => 0,
		'source' => '',
	];

	/**
	 * {@inheritdoc}
	 *
	 * @param Translations $translations Translations instance.
	 * @param array<mixed> $options      Options.
	 * @return string
	 */
	public static function toString( Translations $translations, array $options = [] ) {
		$options += static::$options;
		$domain   = $translations->getDomain() ?: 'messages';
		$messages = static::buildMessages( $translations );

		$configuration = [
			'' => [
				'domain'       => $domain,
				'lang'         => $translations->getLanguage() ?: 'en',
				'plural-forms' => $translations->getHeaders()->get( 'Plural-Forms' ) ?: 'nplurals=2; plural=(n != 1);',
			],
		];

		$data = [
			'translation-revision-date' => $translations->getHeaders()->get( 'PO-Revision-Date' ),
			'generator'                 => 'WP-CLI/' . WP_CLI_VERSION,
			'source'                    => $options['source'],
			'domain'                    => $domain,
			'locale_data'               => [
				$domain => $configuration + $messages,
			],
		];

		$result = json_encode( $data, is_int( $options['json'] ) ? $options['json'] : 0 );
		return false === $result ? '' : $result;
	}

	/**
	 * Generates an array with all translations.
	 *
	 * @param Translations $translations Translations instance.
	 *
	 * @return array<string, array<string>>
	 */
	public static function buildMessages( Translations $translations ) {
		$plural_forms      = $translations->getHeaders()->getPluralForm();
		$number_of_plurals = is_array( $plural_forms ) ? $plural_forms[0] - 1 : null;
		$messages          = [];
		$context_glue      = chr( 4 );

		foreach ( $translations as $translation ) {
			/** @var Translation $translation */

			if ( $translation->isDisabled() ) {
				continue;
			}

			$key = $translation->getOriginal();

			if ( null !== $translation->getContext() && '' !== $translation->getContext() ) {
				$key = $translation->getContext() . $context_glue . $key;
			}

			if ( '' !== implode( '', $translation->getPluralTranslations() ) ) {
				$message = $translation->getPluralTranslations( $number_of_plurals );
				array_unshift( $message, (string) $translation->getTranslation() );
			} else {
				$message = [ (string) $translation->getTranslation() ];
			}

			$messages[ $key ] = $message;
		}

		return $messages;
	}
}
