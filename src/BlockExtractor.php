<?php

namespace WP_CLI\I18n;

use Gettext\Extractors\Extractor;
use Gettext\Extractors\ExtractorInterface;
use Gettext\Translations;
use WP_CLI;

final class BlockExtractor extends Extractor implements ExtractorInterface {
	use IterableCodeExtractor;

	public static $options = [
		'translatableProperties' => [
			'title',
			'description',
			'keywords',
			'styles',
		],
	];

	/**
	 * @inheritdoc
	 */
	public static function fromString( $string, Translations $translations, array $options = [] ) {
		$file = $options['file'];
		WP_CLI::debug( "Parsing file {$file}" );

		$file_data = json_decode( $string, true );

		if ( null === $file_data ) {
			WP_CLI::debug(
				sprintf(
					'Could not parse file %1$s: error code %2$s',
					$file,
					json_last_error()
				)
			);

			return;
		}

		$domain = isset( $file_data['textDomain'] ) ? $file_data['textDomain'] : null;

		// Allow missing domain, but skip if they don't match.
		if ( null !== $domain && $domain !== $translations->getDomain() ) {
			return;
		}

		foreach ( $file_data as $key => $original ) {
			if ( ! array_key_exists( $key, $options['translatableProperties'] ) ) {
				continue;
			}

			foreach ( (array) $original as $msg ) {
				$translation = $translations->insert( sprintf( 'block %s', $key ), $msg );
				$translation->addReference( $file );
			}
		}
	}
}
