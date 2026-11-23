<?php

namespace WP_CLI\I18n;

use Exception;
use WP_CLI;

final class PhpCodeExtractor {
	use IterableCodeExtractor;

	/**
	 * @var array<mixed>
	 */
	public static $options = [
		'extractComments' => [ 'translators', 'Translators' ],
		'constants'       => [],
		'functions'       => [
			'__'              => 'text_domain',
			'esc_attr__'      => 'text_domain',
			'esc_html__'      => 'text_domain',
			'esc_xml__'       => 'text_domain',
			'_e'              => 'text_domain',
			'esc_attr_e'      => 'text_domain',
			'esc_html_e'      => 'text_domain',
			'esc_xml_e'       => 'text_domain',
			'_x'              => 'text_context_domain',
			'_ex'             => 'text_context_domain',
			'esc_attr_x'      => 'text_context_domain',
			'esc_html_x'      => 'text_context_domain',
			'esc_xml_x'       => 'text_context_domain',
			'_n'              => 'single_plural_number_domain',
			'_nx'             => 'single_plural_number_context_domain',
			'_n_noop'         => 'single_plural_domain',
			'_nx_noop'        => 'single_plural_context_domain',

			// Compat.
			'_'               => 'gettext', // Same as 'text_domain'.

			// Deprecated.
			'_c'              => 'text_domain',
			'_nc'             => 'single_plural_number_domain',
			'__ngettext'      => 'single_plural_number_domain',
			'__ngettext_noop' => 'single_plural_domain',
		],
	];

	/**
	 * Extracts translations from PHP code.
	 *
	 * @param string       $text         The code to extract strings from.
	 * @param Translations $translations Translations instance.
	 * @param array<mixed> $options      Extraction options.
	 * @return void
	 */
	public static function fromString( $text, Translations $translations, array $options = [] ) {
		$file = '';
		if ( isset( $options['file'] ) && is_scalar( $options['file'] ) ) {
			$file = (string) $options['file'];
		}
		WP_CLI::debug( "Parsing file {$file}", 'make-pot' );

		try {
			self::scan( $text, $translations, $options + self::$options );
		} catch ( Exception $exception ) {
			WP_CLI::debug(
				sprintf(
					'Could not parse file %1$s: %2$s',
					$file,
					$exception->getMessage()
				),
				'make-pot'
			);
		}
	}

	/**
	 * Scans PHP code for the configured functions and adds the strings found.
	 *
	 * @param string       $text         The code to extract strings from.
	 * @param Translations $translations Translations instance.
	 * @param array<mixed> $options      Extraction options.
	 * @return void
	 */
	public static function scan( $text, Translations $translations, array $options ) {
		/** @var array<string, string> $functions */
		$functions = isset( $options['functions'] ) && is_array( $options['functions'] ) ? $options['functions'] : [];

		$file = isset( $options['file'] ) && is_scalar( $options['file'] ) ? (string) $options['file'] : '';

		$prefixes = false;
		if ( isset( $options['extractComments'] ) && ( is_string( $options['extractComments'] ) || is_array( $options['extractComments'] ) ) ) {
			$prefixes = array_values(
				array_filter(
					(array) $options['extractComments'],
					static function ( $prefix ) {
						return is_string( $prefix ) && '' !== $prefix;
					}
				)
			);
		}

		$add_reference = ! empty( $options['addReferences'] );
		$target_domain = isset( $options['domain'] ) ? $options['domain'] : $translations->getDomain();

		$scanner = new PhpFunctionsScanner( array_keys( $functions ) );

		foreach ( $scanner->scan( $text, $file ) as $function ) {
			$args     = $function->getArguments();
			$original = null;
			$domain   = null;
			$context  = null;
			$plural   = null;

			switch ( $functions[ $function->getName() ] ) {
				case 'text_domain':
				case 'gettext':
					list( $original, $domain ) = array_pad( $args, 2, null );
					break;

				case 'text_context_domain':
					list( $original, $context, $domain ) = array_pad( $args, 3, null );
					break;

				case 'single_plural_number_domain':
					list( $original, $plural, , $domain ) = array_pad( $args, 4, null );
					break;

				case 'single_plural_number_context_domain':
					list( $original, $plural, , $context, $domain ) = array_pad( $args, 5, null );
					break;

				case 'single_plural_domain':
					list( $original, $plural, $domain ) = array_pad( $args, 3, null );
					break;

				case 'single_plural_context_domain':
					list( $original, $plural, $context, $domain ) = array_pad( $args, 4, null );
					break;

				default:
					WP_CLI::error( sprintf( "Internal error: unknown function map '%s' for '%s'.", $functions[ $function->getName() ], $function->getName() ) );
			}

			if ( ! is_string( $original ) || '' === $original ) {
				continue;
			}

			if ( $domain !== $target_domain && null !== $target_domain ) {
				continue;
			}

			$translation = $translations->insert(
				is_string( $context ) ? $context : null,
				$original,
				is_string( $plural ) ? $plural : null
			);

			if ( $add_reference ) {
				$translation->getReferences()->add( $file, $function->getLine() );
			}

			if (
				1 === preg_match( MakePotCommand::SPRINTF_PLACEHOLDER_REGEX, $original ) ||
				1 === preg_match( MakePotCommand::UNORDERED_SPRINTF_PLACEHOLDER_REGEX, $original )
			) {
				$translation->getFlags()->add( 'php-format' );
			}

			if ( false === $prefixes ) {
				continue;
			}

			// Only the comment closest to the function call applies to it.
			foreach ( array_reverse( $function->getComments() ) as $comment ) {
				$comment = ParsedComment::create( $comment, $function->getLine() );

				if ( $comment->checkPrefixes( $prefixes ) ) {
					$translation->getExtractedComments()->add( $comment->getComment() );
					break;
				}
			}
		}
	}
}
