<?php

namespace WP_CLI\I18n;

use Gettext\Generators\Jed;
use Gettext\Translations;

/**
 * Jed file generator.
 *
 * Adds some more meta data to JED translation files than the default generator.
 */
class JedGenerator extends Jed {
	/**
	 * {@parentDoc}.
	 */
	public static function toString( Translations $translations, array $options = [] ) {
		$domain  = $translations->getDomain() ?: 'messages';
		$options += static::$options;

		$locale_data = json_decode( parent::toString( $translations, $options ), true );

		return json_encode(
			[
				'translation-revision-date' => $translations->getHeader( 'PO-Revision-Date' ),
				'generator'                 => 'WP-CLI/' . WP_CLI_VERSION,
				'domain'                    => $domain,
				'locale_data'               => $locale_data,
			],
			$options['json']
		);
	}
}
