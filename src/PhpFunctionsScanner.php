<?php

namespace WP_CLI\I18n;

use Gettext\Translation;

class PhpFunctionsScanner {

	/**
	 * The PHP code to parse.
	 *
	 * @var string
	 */
	protected $code;

	/**
	 * Parsed PHP functions.
	 *
	 * @var array
	 */
	protected $functions = [];

	/**
	 * Constructor.
	 *
	 * @param string $code PHP code to parse.
	 */
	public function __construct( $code ) {
		$this->code = $code;
	}

	/**
	 * Get parsed functions from PHP code.
	 *
	 * @param array $constants Constants to replace.
	 * @return array
	 */
	public function getFunctions( array $constants = [] ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Legacy method name for compatibility.
		if ( empty( $this->functions ) ) {
			$this->functions = $this->parseFunctions( $constants );
		}
		return $this->functions;
	}

	/**
	 * Parse PHP code to extract function calls.
	 *
	 * @param array $constants Constants to replace.
	 * @return array
	 */
	protected function parseFunctions( array $constants = [] ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Legacy method name for compatibility.
		// This is a simplified implementation.
		// The actual parsing is done by PhpScanner in gettext v5.
		unset( $constants ); // Unused parameter, kept for compatibility.
		return [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function saveGettextFunctions( $translations, array $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Legacy method name for compatibility.
		// Ignore multiple translations for now.
		// @todo Add proper support for multiple translations.
		if ( is_array( $translations ) ) {
			$translations = $translations[0];
		}

		$functions     = $options['functions'];
		$file          = $options['file'];
		$add_reference = ! empty( $options['addReferences'] );

		foreach ( $this->getFunctions( $options['constants'] ) as $function ) {
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
					\WP_CLI::error( sprintf( "Internal error: unknown function map '%s' for '%s'.", $functions[ $name ], $name ) );
			}

			if ( '' === (string) $original ) {
				continue;
			}

			if ( $domain !== $translations->getDomain() && null !== $translations->getDomain() ) {
				continue;
			}

			$translation = $translations->addOrMerge( Translation::create( $context, $original ) );

			if ( $plural ) {
				$translation->setPlural( $plural );
			}

			if ( $add_reference ) {
				$translation->getReferences()->add( $file, $line );
			}

			if (
				1 === preg_match( MakePotCommand::SPRINTF_PLACEHOLDER_REGEX, $original ) ||
				1 === preg_match( MakePotCommand::UNORDERED_SPRINTF_PLACEHOLDER_REGEX, $original )
			) {
				$translation->addFlag( 'php-format' );
			}

			if ( isset( $function[3] ) ) {
				foreach ( $function[3] as $extracted_comment ) {
					$translation->getComments()->add( $extracted_comment );
				}
			}
		}
	}
}
