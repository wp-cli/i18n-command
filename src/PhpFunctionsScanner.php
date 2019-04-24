<?php

namespace WP_CLI\I18n;

use Gettext\Translations;
use Gettext\Utils\PhpFunctionsScanner as GettextPhpFunctionsScanner;

class PhpFunctionsScanner extends GettextPhpFunctionsScanner {
	/**
	 * {@inheritdoc}
	 */
	public function saveGettextFunctions( Translations $translations, array $options ) {
		$functions = $options['functions'];
		$file      = $options['file'];

		foreach ( $this->getFunctions( $options['constants'] ) as $function ) {
			list( $name, $line, $args ) = $function;

			if ( ! isset( $functions[ $name ] ) ) {
				continue;
			}

			$context = null;
			$plural  = null;

			switch ( $functions[ $name ] ) {
				case 'text_domain':
				case 'gettext':
					list( $original, $domain ) = array_pad( $args, 2, null );
					break;

				case 'text_context_domain':
					list( $original, $context, $domain ) = array_pad( $args, 3, null );
					break;

				case 'single_plural_number_domain':
					list( $original, $plural, $number, $domain ) = array_pad( $args, 4, null );
					break;

				case 'single_plural_number_context_domain':
					list( $original, $plural, $number, $context, $domain ) = array_pad( $args, 5, null );
					break;

				case 'single_plural_domain':
					list( $original, $plural, $domain ) = array_pad( $args, 3, null );
					break;

				case 'single_plural_context_domain':
					list( $original, $plural, $context, $domain ) = array_pad( $args, 4, null );
					break;

				default:
					// Should never happen.
					\WP_CLI::error( sprintf( "Internal error: unknown function map '%s' for '%s'.", $functions[ $name ], $name ) );
			}

			if ( '' !== (string) $original && ( $domain === $translations->getDomain() || null === $translations->getDomain() ) ) {
				$translation = $translations->insert( $context, $original, $plural );
				$translation = $translation->addReference( $file, $line );

				if ( isset( $function[3] ) ) {
					foreach ( $function[3] as $extracted_comment ) {
						$translation = $translation->addExtractedComment( $extracted_comment );
					}
				}
			}
		}
	}
}
