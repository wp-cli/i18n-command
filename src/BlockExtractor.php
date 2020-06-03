<?php

namespace WP_CLI\I18n;

use Gettext\Extractors\Extractor;
use Gettext\Extractors\ExtractorInterface;
use Gettext\Translations;
use WP_CLI;

final class BlockExtractor extends Extractor implements ExtractorInterface {
	use IterableCodeExtractor;

	/**
	 * @inheritdoc
	 */
	public static function fromString( $string, Translations $translations, array $options = [] ) {
		$file = $options['file'];
		WP_CLI::debug( "Parsing file {$file}", 'make-pot' );

		$file_data = json_decode( $string, true );

		if ( null === $file_data ) {
			WP_CLI::debug(
				sprintf(
					'Could not parse file %1$s: error code %2$s',
					$file,
					json_last_error()
				),
				'make-pot'
			);

			return;
		}

		$domain = isset( $file_data['textdomain'] ) ? $file_data['textdomain'] : null;

		// Allow missing domain, but skip if they don't match.
		if ( null !== $domain && $domain !== $translations->getDomain() ) {
			return;
		}

		foreach ( $file_data as $key => $original ) {
			switch ( $key ) {
				case 'title':
				case 'description':
					$translation = $translations->insert( sprintf( 'block %s', $key ), $original );
					$translation->addReference( $file );
					break;
				case 'keywords':
					if ( ! is_array( $original ) ) {
						continue 2;
					}

					foreach ( $original as $msg ) {
						$translation = $translations->insert( 'block keyword', $msg );
						$translation->addReference( $file );
					}

					break;
				case 'styles':
					if ( ! is_array( $original ) ) {
						continue 2;
					}

					foreach ( $original as $msg ) {
						$translation = $translations->insert( 'block style label', $msg['label'] );
						$translation->addReference( $file );
					}
			}
		}
	}
}
