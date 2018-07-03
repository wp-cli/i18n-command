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

			$domain = $context = $original = $plural = null;

			switch ( $functions[ $name ] ) {
				case 'text_domain':
				case 'gettext':
					if ( ! isset( $args[1] ) ) {
						continue 2;
					}

					list( $original, $domain ) = $args;
					break;

				case 'text_context_domain':
					if ( ! isset( $args[2] ) ) {
						continue 2;
					}

					list( $original, $context, $domain ) = $args;
					break;

				case 'single_plural_number_domain':
					if ( ! isset( $args[3] ) ) {
						continue 2;
					}

					list( $original, $plural, $number, $domain ) = $args;
					break;

				case 'single_plural_number_context_domain':
					if ( ! isset( $args[4] ) ) {
						continue 2;
					}

					list( $original, $plural, $number, $context, $domain ) = $args;
					break;

				case 'single_plural_domain':
					if ( ! isset( $args[2] ) ) {
						continue 2;
					}

					list( $original, $plural, $domain ) = $args;
					break;

				case 'single_plural_context_domain':
					if ( ! isset( $args[3] ) ) {
						continue 2;
					}

					list( $original, $plural, $context, $domain ) = $args;
					break;

				default:
					// Should never happen.
					\WP_CLI::error( sprintf( "Internal error: unknown function map '%s' for '%s'.", $functions[ $name ], $name ) );
			}

			// Todo: Require a domain?
			if ( (string) $original !== '' && ( $domain === null || $domain === $translations->getDomain() ) ) {
				$translation = $translations->insert( $context, $original, $plural );
				$translation = $translation->addReference( $file, $line );

				if ( isset( $function[3] ) ) {
					foreach ( $function[3] as $extractedComment ) {
						$translation = $translation->addExtractedComment( $extractedComment );
					}
				}
			}
		}
	}
}
