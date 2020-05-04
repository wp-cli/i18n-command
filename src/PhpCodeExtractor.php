<?php

namespace WP_CLI\I18n;

use Exception;
use Gettext\Scanner\PhpScanner;
use Gettext\Translations;
use WP_CLI;

class PhpCodeExtractor {
	use IterableCodeExtractor;

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
	 * {@inheritdoc}
	 */
	public static function fromString( $string, Translations $translations, array $options = [] ) {
		WP_CLI::debug( "Parsing file {$options['file']}", 'make-pot' );

		try {
			$scanner = new PhpScanner( $translations );
			$scanner->setFunctions( self::$options['functions'] );
			$scanner->extractCommentsStartingWith($options['extractComments']);
			$scanner->scanString( $string, $options[ 'file' ] );
		} catch ( Exception $exception ) {
			WP_CLI::debug(
				sprintf(
					'Could not parse file %1$s: %2$s',
					$options['file'],
					$exception->getMessage()
				),
				'make-pot'
			);
		}
	}
}
