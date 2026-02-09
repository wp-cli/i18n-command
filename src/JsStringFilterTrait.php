<?php

namespace WP_CLI\I18n;

use Gettext\Translation;
use Gettext\Translations;

trait JsStringFilterTrait {
	/**
	 * JavaScript file extensions to check for.
	 *
	 * @var string[]
	 */
	protected static $js_extensions = [ '.js', '.jsx', '.ts', '.tsx', '.mjs', '.cjs' ];

	/**
	 * Removes strings from translations that only occur in JavaScript files.
	 *
	 * @param Translations $translations The translations instance to filter.
	 */
	protected function remove_js_only_strings( $translations ) {
		foreach ( $translations->getArrayCopy() as $translation ) {
			/** @var Translation $translation */

			if ( ! $translation->hasReferences() ) {
				continue;
			}

			$has_non_js_reference = false;
			foreach ( $translation->getReferences() as $reference ) {
				$file      = $reference[0];
				$extension = '.' . pathinfo( $file, PATHINFO_EXTENSION );

				if ( ! in_array( $extension, self::$js_extensions, true ) ) {
					$has_non_js_reference = true;
					break;
				}
			}

			// If all references are JS files, remove this translation.
			if ( ! $has_non_js_reference ) {
				unset( $translations[ $translation->getId() ] );
			}
		}
	}
}
