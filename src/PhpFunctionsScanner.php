<?php

namespace WP_CLI\I18n;

/**
 * Finds function calls and their string arguments in PHP code.
 *
 * Based on the PHP functions scanner of gettext/gettext 4.x.
 */
class PhpFunctionsScanner {

	/**
	 * PHP tokens of the code to be parsed.
	 *
	 * @var array<int, string|array{0: int, 1: string, 2: int}>
	 */
	protected $tokens;

	/**
	 * If not false, comments will be extracted.
	 *
	 * @var string|false|array<string>
	 */
	protected $extract_comments = false;

	/**
	 * @param string $code The PHP code to scan.
	 */
	public function __construct( $code ) {
		$this->tokens = array_values(
			array_filter(
				token_get_all( $code ),
				static function ( $token ) {
					return ! is_array( $token ) || T_WHITESPACE !== $token[0];
				}
			)
		);
	}

	/**
	 * Enable extracting comments that start with a tag (if $tag is empty all the comments will be extracted).
	 *
	 * @param string|array<string> $tag Tag to extract.
	 * @return void
	 */
	public function enableCommentsExtraction( $tag = '' ) {
		$this->extract_comments = $tag;
	}

	/**
	 * Disable comments extraction.
	 *
	 * @return void
	 */
	public function disableCommentsExtraction() {
		$this->extract_comments = false;
	}

	/**
	 * Scans the code and returns the function calls and their arguments.
	 *
	 * @param array<string, string> $constants Constants used in the code to replace.
	 * @return array<int, array{0: string, 1: int, 2: array<int, string|null>, 3: ParsedComment[]|null}> List of name, line, arguments and comments.
	 */
	public function getFunctions( array $constants = [] ) {
		$count = count( $this->tokens );

		/** @var ParsedFunction[] $buffer_functions */
		$buffer_functions = [];

		/** @var ParsedComment[] $buffer_comments */
		$buffer_comments = [];

		$functions = [];

		for ( $k = 0; $k < $count; ++$k ) {
			$value = $this->tokens[ $k ];

			if ( is_string( $value ) ) {
				if ( isset( $buffer_functions[0] ) ) {
					switch ( $value ) {
						case ',':
							$buffer_functions[0]->nextArgument();
							break;

						case ')':
							$functions[] = $buffer_functions[0]->close();
							array_shift( $buffer_functions );
							break;

						case '.':
							break;

						default:
							$buffer_functions[0]->stopArgument();
							break;
					}
				}

				continue;
			}

			// phpcs:ignore PHPCompatibility.Constants.NewConstants.t_name_fully_qualifiedFound
			if ( defined( 'T_NAME_FULLY_QUALIFIED' ) && T_NAME_FULLY_QUALIFIED === $value[0] ) {
				$value[0] = T_STRING;
				$value[1] = ltrim( $value[1], '\\' );
			}

			switch ( $value[0] ) {
				case T_CONSTANT_ENCAPSED_STRING:
					// Add an argument to the current function.
					if ( isset( $buffer_functions[0] ) ) {
						$buffer_functions[0]->addArgumentChunk( self::convertString( $value[1] ) );
					}
					break;

				case T_STRING:
					if ( isset( $buffer_functions[0] ) ) {
						if ( isset( $constants[ $value[1] ] ) ) {
							$buffer_functions[0]->addArgumentChunk( $constants[ $value[1] ] );
							break;
						}

						if ( 'null' === strtolower( $value[1] ) ) {
							$buffer_functions[0]->addArgumentChunk( null );
							break;
						}

						$buffer_functions[0]->stopArgument();
					}

					// New function found.
					for ( $j = $k + 1; $j < $count; ++$j ) {
						$next_token = $this->tokens[ $j ];

						if ( is_array( $next_token ) && T_COMMENT === $next_token[0] ) {
							continue;
						}

						if ( '(' === $next_token ) {
							$new_function = new ParsedFunction( $value[1], $value[2] );

							// Add comment that was on the line before.
							if ( isset( $buffer_comments[0] ) && $buffer_comments[0]->isRelatedWith( $new_function ) ) {
								$new_function->addComment( $buffer_comments[0] );
							}

							array_unshift( $buffer_functions, $new_function );
							$k = $j;
						}

						break;
					}
					break;

				case T_COMMENT:
					$comment = $this->parsePhpComment( $value[1], $value[2] );

					if ( $comment ) {
						array_unshift( $buffer_comments, $comment );

						// The comment is inside the function call.
						if ( isset( $buffer_functions[0] ) ) {
							$buffer_functions[0]->addComment( $comment );
						}
					}
					break;

				default:
					if ( isset( $buffer_functions[0] ) ) {
						$buffer_functions[0]->stopArgument();
					}
					break;
			}
		}

		return $functions;
	}

	/**
	 * Extracts the actual text from a PHP comment.
	 *
	 * @param string $value The PHP comment.
	 * @param int    $line  Line number.
	 * @return ParsedComment|null Comment or null if comment extraction is disabled or if there is a prefix mismatch.
	 */
	protected function parsePhpComment( $value, $line ) {
		if ( false === $this->extract_comments ) {
			return null;
		}

		$comment  = ParsedComment::create( $value, $line );
		$prefixes = array_filter( (array) $this->extract_comments );

		return $comment->checkPrefixes( $prefixes ) ? $comment : null;
	}

	/**
	 * Decodes a T_CONSTANT_ENCAPSED_STRING string.
	 *
	 * @param string $value String literal including its quotes.
	 * @return string
	 */
	public static function convertString( $value ) {
		if ( false === strpos( $value, '\\' ) ) {
			return substr( $value, 1, -1 );
		}

		if ( "'" === $value[0] ) {
			return strtr(
				substr( $value, 1, -1 ),
				[
					'\\\\' => '\\',
					'\\\'' => '\'',
				]
			);
		}

		$value = substr( $value, 1, -1 );

		return (string) preg_replace_callback(
			'/\\\(n|r|t|v|e|f|\$|"|\\\|x[0-9A-Fa-f]{1,2}|u{[0-9a-f]{1,6}}|[0-7]{1,3})/',
			static function ( $matches ) {
				switch ( $matches[1][0] ) {
					case 'n':
						return "\n";
					case 'r':
						return "\r";
					case 't':
						return "\t";
					case 'v':
						return "\v";
					case 'e':
						return "\e";
					case 'f':
						return "\f";
					case '$':
						return '$';
					case '"':
						return '"';
					case '\\':
						return '\\';
					case 'x':
						return chr( (int) hexdec( substr( $matches[1], 1 ) ) & 0xFF );
					case 'u':
						return (string) self::unicodeChar( (int) hexdec( trim( substr( $matches[1], 1 ), '{}' ) ) );
					default:
						return chr( (int) octdec( $matches[1] ) & 0xFF );
				}
			},
			$value
		);
	}

	/**
	 * Returns the UTF-8 encoded character for a Unicode code point.
	 *
	 * @param int $dec Code point.
	 * @return string|null
	 */
	protected static function unicodeChar( $dec ) {
		if ( $dec < 0x80 ) {
			return chr( $dec & 0x7F );
		}

		if ( $dec < 0x0800 ) {
			return chr( 0xC0 + ( $dec >> 6 ) )
				. chr( 0x80 + ( $dec & 0x3f ) );
		}

		if ( $dec < 0x010000 ) {
			return chr( 0xE0 + ( $dec >> 12 ) )
				. chr( 0x80 + ( ( $dec >> 6 ) & 0x3f ) )
				. chr( 0x80 + ( $dec & 0x3f ) );
		}

		if ( $dec < 0x200000 ) {
			return chr( 0xF0 + ( $dec >> 18 ) )
				. chr( 0x80 + ( ( $dec >> 12 ) & 0x3f ) )
				. chr( 0x80 + ( ( $dec >> 6 ) & 0x3f ) )
				. chr( 0x80 + ( $dec & 0x3f ) );
		}

		return null;
	}

	/**
	 * Searches for specific functions and creates translations.
	 *
	 * @param Translations $translations Translations instance.
	 * @param array<mixed>         $options      Options.
	 * @return void
	 */
	public function saveGettextFunctions( Translations $translations, array $options ) {
		$functions = isset( $options['functions'] ) ? $options['functions'] : [];
		if ( ! is_array( $functions ) ) {
			$functions = [];
		}

		$file = '';
		if ( isset( $options['file'] ) && is_scalar( $options['file'] ) ) {
			$file = (string) $options['file'];
		}

		$add_reference = ! empty( $options['addReferences'] );

		$constants = isset( $options['constants'] ) ? $options['constants'] : [];
		if ( ! is_array( $constants ) ) {
			$constants = [];
		}

		/** @var array<string, string> $constants */

		foreach ( $this->getFunctions( $constants ) as $function ) {
			list( $name, $line, $args ) = $function;

			if ( ! isset( $functions[ $name ] ) ) {
				continue;
			}

			$original = null;
			$domain   = null;
			$context  = null;
			$plural   = null;

			switch ( $functions[ $name ] ) {
				case 'text_domain':
				case 'gettext':
					list( $original, $domain ) = array_pad( $args, 2, null );
					break;

				case 'text_context_domain':
					list( $original, $context, $domain ) = array_pad( $args, 3, null );
					break;

				case 'single_plural_number_domain':
					list( $original, $plural, $number, $domain ) = array_pad( $args, 4, null );
					break;

				case 'single_plural_number_context_domain':
					list( $original, $plural, $number, $context, $domain ) = array_pad( $args, 5, null );
					break;

				case 'single_plural_domain':
					list( $original, $plural, $domain ) = array_pad( $args, 3, null );
					break;

				case 'single_plural_context_domain':
					list( $original, $plural, $context, $domain ) = array_pad( $args, 4, null );
					break;

				default:
					// Should never happen.
					$func_map     = isset( $functions[ $name ] ) ? $functions[ $name ] : '';
					$func_map_str = is_scalar( $func_map ) ? (string) $func_map : '';
					$name_str     = is_scalar( $name ) ? (string) $name : '';
					\WP_CLI::error( sprintf( "Internal error: unknown function map '%s' for '%s'.", $func_map_str, $name_str ) );
			}

			$original_str = '';
			if ( is_scalar( $original ) ) {
				$original_str = (string) $original;
			}

			if ( '' === $original_str ) {
				continue;
			}

			$target_domain = isset( $options['domain'] ) ? $options['domain'] : $translations->getDomain();
			if ( $domain !== $target_domain && null !== $target_domain ) {
				continue;
			}

			$translation = $translations->insert( $context, $original_str, $plural );

			if ( $add_reference ) {
				$translation->getReferences()->add( $file, $line );
			}

			if (
				1 === preg_match( MakePotCommand::SPRINTF_PLACEHOLDER_REGEX, $original_str ) ||
				1 === preg_match( MakePotCommand::UNORDERED_SPRINTF_PLACEHOLDER_REGEX, $original_str )
			) {
				$translation->getFlags()->add( 'php-format' );
			}

			foreach ( $function[3] ?? [] as $extracted_comment ) {
				$translation->getExtractedComments()->add( $extracted_comment->getComment() );
			}
		}
	}
}
