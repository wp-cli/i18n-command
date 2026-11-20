<?php

namespace WP_CLI\I18n;

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
	protected function remove_js_only_strings( Translations $translations ): void {
		foreach ( $translations->getTranslations() as $translation ) {
			$files = array_keys( $translation->getReferences()->toArray() );

			if ( empty( $files ) ) {
				continue;
			}

			$has_non_js_reference = false;
			foreach ( $files as $file ) {
				$extension = '.' . strtolower( pathinfo( (string) $file, PATHINFO_EXTENSION ) );

				if ( ! in_array( $extension, static::$js_extensions, true ) ) {
					$has_non_js_reference = true;
					break;
				}
			}

			// If all references are JS files, remove this translation.
			if ( ! $has_non_js_reference ) {
				$translations->remove( $translation );
			}
		}
	}
}
