<?php

namespace WP_CLI\I18n;

use Exception;
use Gettext\Scanner\JsScanner;
use Gettext\Translations;
use Peast\Syntax\Exception as PeastException;
use WP_CLI;

final class JsCodeExtractor {
	use IterableCodeExtractor;

	protected $functions = [
		'__'  => 'text_domain',
		'_x'  => 'text_context_domain',
		'_n'  => 'single_plural_number_domain',
		'_nx' => 'single_plural_number_context_domain',
	];

	public static $options = [
		'extractComments' => [ 'translators', 'Translators' ],
		'constants'       => [],
		'functions'       => [],
	];

	/**
	 * @inheritdoc
	 */
	public static function fromString( $text, Translations $translations, array $options = [] ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Using gettext scanner API.
		WP_CLI::debug( "Parsing file {$options['file']}", 'make-pot' );

		try {
			$options += self::$options;

			$scanner = new JsScanner( $translations );
			$scanner->setFunctions( self::$options['functions'] );
			$scanner->extractCommentsStartingWith( ...$options['extractComments'] );
			$scanner->scanString( $text, $options['file'] );
		} catch ( PeastException $exception ) {
			WP_CLI::debug(
				sprintf(
					'Could not parse file %1$s: %2$s (line %3$d, column %4$d)',
					$options['file'],
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
					$options['file'],
					$exception->getMessage()
				),
				'make-pot'
			);
		}
	}
}
