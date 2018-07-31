<?php

namespace WP_CLI\I18n;

use Gettext\Extractors\JsCode;
use Gettext\Translations;
use Peast\Syntax\Exception as PeastException;
use WP_CLI;

final class JsCodeExtractor extends JsCode {
	use IterableCodeExtractor;

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
	 * {@inheritdoc}
	 */
	public static function fromString( $string, Translations $translations, array $options = [] ) {
		try {
			$options += static::$options;

			$functions = new JsFunctionsScanner( $string );

			$functions->enableCommentsExtraction( $options['extractComments'] );
			$functions->saveGettextFunctions( $translations, $options );
		} catch ( PeastException $e ) {
			WP_CLI::debug(
				sprintf(
					'Could not parse file %1$s: %2$s (line %3$d, column %4$d)',
					$options['file'],
					$e->getMessage(),
					$e->getPosition()->getLine(),
					$e->getPosition()->getColumn()
				)
			);
		}
	}
}
