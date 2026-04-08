<?php

namespace WP_CLI\I18n;

use Gettext\Utils\PhpFunctionsScanner as GettextPhpFunctionsScanner;

class PhpFunctionsScanner extends GettextPhpFunctionsScanner {

	/**
	 * {@inheritdoc}
	 *
	 * @param \Gettext\Translations $translations Translations instance.
	 * @param array<mixed>         $options      Options.
	 * @return void
	 */
	public function saveGettextFunctions( $translations, array $options ) {
		// Ignore multiple translations for now.
		// @todo Add proper support for multiple translations.
		if ( is_array( $translations ) ) {
			$translations = $translations[0];
		}

		$functions = isset( $options['functions'] ) ? $options['functions'] : [];
		if ( ! is_array( $functions ) ) {
			$functions = [];
		}

		$file = '';
		if ( isset( $options['file'] ) && is_scalar( $options['file'] ) ) {
			$file = (string) $options['file'];
		}

		$add_reference = ! empty( $options['addReferences'] );

		$constants = isset( $options['constants'] ) ? $options['constants'] : [];
		if ( ! is_array( $constants ) ) {
			$constants = [];
		}

		foreach ( $this->getFunctions( $constants ) as $function ) {
			list( $name, $line, $args ) = $function;

			if ( ! isset( $functions[ $name ] ) ) {
				continue;
			}

			$original = null;
			$domain   = null;
			$context  = null;
			$plural   = null;

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
					$func_map     = isset( $functions[ $name ] ) ? $functions[ $name ] : '';
					$func_map_str = is_scalar( $func_map ) ? (string) $func_map : '';
					$name_str     = is_scalar( $name ) ? (string) $name : '';
					\WP_CLI::error( sprintf( "Internal error: unknown function map '%s' for '%s'.", $func_map_str, $name_str ) );
			}

			$original_str = '';
			if ( is_scalar( $original ) ) {
				$original_str = (string) $original;
			}

			if ( '' === $original_str ) {
				continue;
			}

			$target_domain = isset( $options['domain'] ) ? $options['domain'] : $translations->getDomain();
			if ( $domain !== $target_domain && null !== $target_domain ) {
				continue;
			}

			$translation = $translations->insert( $context, $original_str, $plural );

			if ( $add_reference ) {
				$translation = $translation->addReference( $file, $line );
			}

			if (
				1 === preg_match( MakePotCommand::SPRINTF_PLACEHOLDER_REGEX, $original_str ) ||
				1 === preg_match( MakePotCommand::UNORDERED_SPRINTF_PLACEHOLDER_REGEX, $original_str )
			) {
				$translation->addFlag( 'php-format' );
			}

			if ( isset( $function[3] ) && is_array( $function[3] ) ) {
				foreach ( $function[3] as $extracted_comment ) {
					if ( is_scalar( $extracted_comment ) ) {
						$translation = $translation->addExtractedComment( (string) $extracted_comment );
					} elseif ( is_object( $extracted_comment ) && method_exists( $extracted_comment, 'getComment' ) ) {
						$translation = $translation->addExtractedComment( $extracted_comment->getComment() );
					}
				}
			}
		}
	}
}
