<?php

namespace WP_CLI\I18n;

use Exception;
use Peast\Syntax\Exception as PeastException;
use WP_CLI;

final class JsCodeExtractor {
	use IterableCodeExtractor;

	/**
	 * @var array<mixed>
	 */
	public static $options = [
		'extractComments' => [ 'translators', 'Translators' ],
		'constants'       => [],
		'functions'       => [
			'__'  => 'text_domain',
			'_x'  => 'text_context_domain',
			'_n'  => 'single_plural_number_domain',
			'_nx' => 'single_plural_number_context_domain',
		],
	];

	/**
	 * Extracts translations from JavaScript code.
	 *
	 * @param string       $text         The text to extract strings from.
	 * @param Translations $translations Translations instance.
	 * @param array<mixed> $options      Extraction options.
	 * @return void
	 */
	public static function fromString( $text, Translations $translations, array $options = [] ) {
		$file = isset( $options['file'] ) && is_scalar( $options['file'] ) ? (string) $options['file'] : '';
		WP_CLI::debug( "Parsing file {$file}", 'make-pot' );

		try {
			$options += self::$options;

			$scanner = new JsFunctionsScanner( $text );

			if ( isset( $options['extractComments'] ) && ( is_string( $options['extractComments'] ) || is_array( $options['extractComments'] ) ) ) {
				/** @var array<string>|string $extract_comments */
				$extract_comments = $options['extractComments'];
				$scanner->enableCommentsExtraction( $extract_comments );
			}

			$scanner->saveGettextFunctions( $translations, $options );
		} catch ( PeastException $exception ) {
			WP_CLI::debug(
				sprintf(
					'Could not parse file %1$s: %2$s (line %3$d, column %4$d)',
					$file,
					$exception->getMessage(),
					$exception->getPosition()->getLine(),
					$exception->getPosition()->getColumn()
				),
				'make-pot'
			);
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
}
