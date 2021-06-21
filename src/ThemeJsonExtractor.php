<?php

namespace WP_CLI\I18n;

use Gettext\Extractors\Extractor;
use Gettext\Extractors\ExtractorInterface;
use Gettext\Translations;
use WP_CLI;

final class ThemeJsonExtractor extends Extractor implements ExtractorInterface {
	use IterableCodeExtractor;

	/**
	 * @inheritdoc
	 */
	public static function fromString( $string, Translations $translations, array $options = [] ) {
        // TODO: IMPLEMENT.
	}
}
