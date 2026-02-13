<?php

namespace WP_CLI\I18n\Tests;

use Gettext\Translations;
use WP_CLI\I18n\BladeCodeExtractor;
use WP_CLI\Tests\TestCase;

class BladeGettextExtractorTest extends TestCase {

	/**
	 * Helper to extract translations from a Blade string.
	 *
	 * @param string $blade  Blade template content.
	 * @param string $domain Text domain.
	 * @return Translations
	 */
	private function extract( $blade, $domain = 'foo-theme' ) {
		$translations = new Translations();
		$translations->setDomain( $domain );

		$options = array_merge(
			BladeCodeExtractor::$options,
			[ 'file' => 'test.blade.php' ]
		);

		BladeCodeExtractor::fromString( $blade, $translations, $options );

		return $translations;
	}

	public function test_extracts_bound_prop_with_translation_function() {
		$translations = $this->extract(
			'<x-alert :message="__(\'Hello\', \'foo-theme\')" />'
		);

		$this->assertNotFalse( $translations->find( null, 'Hello' ) );
	}

	public function test_extracts_multiple_bound_props() {
		$translations = $this->extract(
			'<x-no-results :title="__(\'Not found\', \'foo-theme\')" :subtitle="__(\'Try again\', \'foo-theme\')" />'
		);

		$this->assertNotFalse( $translations->find( null, 'Not found' ) );
		$this->assertNotFalse( $translations->find( null, 'Try again' ) );
	}

	public function test_extracts_bound_props_from_multiline_component_tag() {
		$blade = <<<'BLADE'
<x-no-results
    :title="__('Page not found', 'foo-theme')"
    :subtitle="esc_html__('Please try again', 'foo-theme')"
/>
BLADE;

		$translations = $this->extract( $blade );

		$this->assertNotFalse( $translations->find( null, 'Page not found' ) );
		$this->assertNotFalse( $translations->find( null, 'Please try again' ) );
	}

	public function test_extracts_bound_props_from_open_component_tag() {
		$blade = <<<'BLADE'
<x-alert :message="__('Warning message', 'foo-theme')">
    {!! __('Content inside', 'foo-theme') !!}
</x-alert>
BLADE;

		$translations = $this->extract( $blade );

		$this->assertNotFalse( $translations->find( null, 'Warning message' ) );
		$this->assertNotFalse( $translations->find( null, 'Content inside' ) );
	}

	public function test_ignores_static_props() {
		$translations = $this->extract(
			'<x-alert type="warning" :message="__(\'Hello\', \'foo-theme\')" />'
		);

		$this->assertNotFalse( $translations->find( null, 'Hello' ) );
		$this->assertFalse( $translations->find( null, 'warning' ) );
	}

	public function test_does_not_match_non_component_html() {
		$translations = $this->extract(
			'<a href="https://example.com">{{ __(\'Link text\', \'foo-theme\') }}</a>'
		);

		$this->assertNotFalse( $translations->find( null, 'Link text' ) );
		// Only 1 translation should exist.
		$this->assertCount( 1, $translations );
	}

	public function test_extracts_context_function_in_prop() {
		$translations = $this->extract(
			'<x-button :label="_x(\'Read\', \'verb\', \'foo-theme\')" />'
		);

		$translation = $translations->find( 'verb', 'Read' );
		$this->assertNotFalse( $translation );
	}

	public function test_extracts_esc_functions_in_props() {
		$blade = <<<'BLADE'
<x-field
    :label="esc_html__('Username', 'foo-theme')"
    :placeholder="esc_attr__('Enter username', 'foo-theme')"
/>
BLADE;

		$translations = $this->extract( $blade );

		$this->assertNotFalse( $translations->find( null, 'Username' ) );
		$this->assertNotFalse( $translations->find( null, 'Enter username' ) );
	}

	public function test_existing_blade_extraction_still_works() {
		$blade = <<<'BLADE'
@php
    __('PHP block string', 'foo-theme');
@endphp
{{ __('Echo string', 'foo-theme') }}
{!! __('Raw string', 'foo-theme') !!}
@php(__('Directive string', 'foo-theme'))
BLADE;

		$translations = $this->extract( $blade );

		$this->assertNotFalse( $translations->find( null, 'PHP block string' ) );
		$this->assertNotFalse( $translations->find( null, 'Echo string' ) );
		$this->assertNotFalse( $translations->find( null, 'Raw string' ) );
		$this->assertNotFalse( $translations->find( null, 'Directive string' ) );
	}
}
