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
		$scanner = new PhpFunctionsScanner( $text );

		$extract_comments = $options['extractComments'] ?? false;
		if ( is_string( $extract_comments ) || is_array( $extract_comments ) ) {
			/** @var string|array<string> $extract_comments */
			$scanner->enableCommentsExtraction( $extract_comments );
		}

		$scanner->saveGettextFunctions( $translations, $options );
	}
}
