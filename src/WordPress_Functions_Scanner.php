<?php

namespace WP_CLI\Makepot;

use Gettext\Translations;
use Gettext\Utils\PhpFunctionsScanner;

class WordPress_Functions_Scanner extends PhpFunctionsScanner {
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
				case 'pgettext':
					if ( ! isset( $args[1] ) ) {
						continue 2;
					}

					list( $original, $context ) = $args;
					break;

				case 'dgettext':
					if ( ! isset( $args[1] ) ) {
						continue 2;
					}

					list( $original, $domain ) = $args;
					break;

				case 'dpgettext':
					if ( ! isset( $args[2] ) ) {
						continue 2;
					}

					list( $original, $context, $domain ) = $args;
					break;

				case 'npgettext':
					if ( ! isset( $args[2] ) ) {
						continue 2;
					}

					list( $original, $plural, $context ) = $args;
					break;

				case 'dnpgettext':
					if ( ! isset( $args[3] ) ) {
						continue 2;
					}

					list( $original, $plural, $context, $domain ) = $args;
					break;

				case 'dngettext':
					if ( ! isset( $args[2] ) ) {
						continue 2;
					}

					list( $original, $plural, $domain ) = $args;
					break;

				default:
					parent::saveGettextFunctions( $translations, $options );
					return;
			}

			// Todo: Require a domain?
			if ( (string) $original !== '' && ( $domain === null || $domain === $translations->getDomain() ) ) {
				$translation = $translations->insert( $context, $original, $plural );
				$translation->addReference( $file, $line );

				if ( isset( $function[3] ) ) {
					foreach ( $function[3] as $extractedComment ) {
						$translation->addExtractedComment( $extractedComment );
					}
				}
			}
		}
	}
}
