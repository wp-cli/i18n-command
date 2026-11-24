<?php

namespace WP_CLI\I18n;

/**
 * A source code comment found while scanning for translatable strings.
 */
class ParsedComment {

	/**
	 * @var string
	 */
	protected $comment;

	/**
	 * @var int
	 */
	protected $first_line;

	/**
	 * @var int
	 */
	protected $last_line;

	/**
	 * @param string $comment    The comment itself.
	 * @param int    $first_line The line where the comment starts.
	 * @param int    $last_line  The line where the comment ends.
	 */
	final public function __construct( $comment, $first_line, $last_line ) {
		$this->comment    = $comment;
		$this->first_line = $first_line;
		$this->last_line  = $last_line;
	}

	/**
	 * Creates a new instance from a raw comment.
	 *
	 * @param string $value The comment as found in the source code.
	 * @param int    $line  The line where the comment starts.
	 * @return static
	 */
	public static function create( $value, $line ) {
		$last_line = $line + substr_count( $value, "\n" );

		$lines = array_map(
			static function ( $line ) {
				if ( '' === trim( $line ) ) {
					return null;
				}

				$line = ltrim( $line, "#*/ \t" );
				$line = rtrim( $line, "#*/ \t" );

				return trim( $line );
			},
			explode( "\n", $value )
		);

		// Remove empty lines.
		$lines = array_filter( $lines );

		return new static( implode( ' ', $lines ), $line, $last_line );
	}

	/**
	 * @return int
	 */
	public function getFirstLine() {
		return $this->first_line;
	}

	/**
	 * @return int
	 */
	public function getLastLine() {
		return $this->last_line;
	}

	/**
	 * @return string
	 */
	public function getComment() {
		return $this->comment;
	}

	/**
	 * Whether the comment starts with one of the given prefixes.
	 *
	 * @param array<string> $prefixes Prefixes to check. Every non-empty comment matches if there are none.
	 * @return bool
	 */
	public function checkPrefixes( array $prefixes ) {
		if ( '' === $this->comment ) {
			return false;
		}

		if ( empty( $prefixes ) ) {
			return true;
		}

		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $this->comment, $prefix ) ) {
				return true;
			}
		}

		return false;
	}
}
