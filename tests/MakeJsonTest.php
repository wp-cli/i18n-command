<?php


namespace WP_CLI\I18n\Tests;

use ReflectionClass;
use ReflectionMethod;
use WP_CLI\I18n\MakeJsonCommand;
use WP_CLI\Tests\TestCase;
use WP_CLI\Utils;

class MakeJsonTest extends TestCase {
	/** @var string A path files are located */
	private static $base;
	/** @var \PHPUnit\Framework\MockObject\MockObject MakeJsonCommand partial mock */
	private static $mock = null;
	/** @var \ReflectionMethod make_json reflection (private) */
	private static $make_json = null;

	public function set_up() {
		parent::set_up();

		/**
		 * PHP5.4 cannot set property with __DIR__ constant.
		 * Shamelessly stolen from @see IterableCodeExtractorTest.php
		 */
		self::$base = Utils\normalize_path( __DIR__ ) . '/data/make_json/';

		self::$mock = $this->createPartialMock( MakeJsonCommand::class, [ 'build_json_files' ] );

		$reflection      = new ReflectionClass( self::$mock );
		self::$make_json = $reflection->getMethod( 'make_json' );
		self::$make_json->setAccessible( true );
	}

	public function test_should_pass_array_of_extensions() {
		$mock = $this->createPartialMock( MakeJsonCommand::class, [ 'make_json' ] );

		$mock
			->expects( $this->once() )
			->method( 'make_json' )
			->with(
				$this->isType( 'string' ),
				'foo',
				null,
				'',
				[ 'ts', 'tsx' ]
			)
			->willReturn( [] );

		$mock->__invoke(
			[
				self::$base . 'empty.po',
				'foo',
			],
			[
				'extensions' => '.ts, .tsx',
			]
		);
	}

	public function test_no_custom_extensions() {
		self::$mock
			->expects( $this->once() )
			->method( 'build_json_files' )
			->with(
				$this->callback(
					function ( $mapping ) {
						$this->assertEquals( array_keys( $mapping ), [ 'baz.js', 'qux.js' ] );

						return true;
					}
				),
				$this->isType( 'string' ),
				$this->isType( 'string' )
			)
			->willReturn( [] );

		self::$make_json->invoke( self::$mock, self::$base . 'translations.po', 'bar', null, '', [] );
	}

	public function test_with_custom_extensions() {
		self::$mock
			->expects( $this->once() )
			->method( 'build_json_files' )
			->with(
				$this->callback(
					function ( $mapping ) {
						$this->assertEquals( array_keys( $mapping ), [ 'foo.ts', 'bar.tag', 'baz.js', 'qux.js' ] );

						return true;
					}
				),
				$this->isType( 'string' ),
				$this->isType( 'string' )
			)
			->willReturn( [] );

		self::$make_json->invoke( self::$mock, self::$base . 'translations.po', 'bar', null, '', [ 'tag', 'ts' ] );
	}
}
