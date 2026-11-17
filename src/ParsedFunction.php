<?php

namespace WP_CLI\I18n;

/**
 * A function call found while scanning PHP code for translatable strings.
 */
class ParsedFunction {

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var int
	 */
	protected $line;

	/**
	 * @var array<int, string|null>
	 */
	protected $arguments = [];

	/**
	 * @var int
	 */
	protected $argument_index = -1;

	/**
	 * Whether the current argument can no longer be a plain string.
	 *
	 * @var bool
	 */
	protected $argument_stopped = false;

	/**
	 * @var ParsedComment[]|null
	 */
	protected $comments;

	/**
	 * @param string $name The function name.
	 * @param int    $line The line where the function call starts.
	 */
	public function __construct( $name, $line ) {
		$this->name = $name;
		$this->line = $line;
	}

	/**
	 * Stops extracting strings for the current argument.
	 *
	 * @return void
	 */
	public function stopArgument() {
		if ( -1 === $this->argument_index ) {
			$this->argument_index = 0;
		}

		$this->argument_stopped = true;
	}

	/**
	 * Moves on to the next argument.
	 *
	 * @return void
	 */
	public function nextArgument() {
		if ( -1 === $this->argument_index ) {
			$this->argument_index = 1;
		} else {
			++$this->argument_index;
		}

		$this->argument_stopped = false;
	}

	/**
	 * Adds a string to the current argument.
	 *
	 * @param string|null $chunk Part of the argument.
	 * @return void
	 */
	public function addArgumentChunk( $chunk ) {
		if ( $this->argument_stopped ) {
			return;
		}

		if ( -1 === $this->argument_index ) {
			$this->argument_index = 0;
		}

		if ( isset( $this->arguments[ $this->argument_index ] ) ) {
			$this->arguments[ $this->argument_index ] .= $chunk;
		} else {
			$this->arguments[ $this->argument_index ] = $chunk;
		}
	}

	/**
	 * Adds a comment related to this function call.
	 *
	 * @param ParsedComment $comment The comment.
	 * @return void
	 */
	public function addComment( ParsedComment $comment ) {
		if ( null === $this->comments ) {
			$this->comments = [];
		}

		$this->comments[] = $comment;
	}

	/**
	 * @return int
	 */
	public function getLine() {
		return $this->line;
	}

	/**
	 * Returns the function call once all its arguments have been read.
	 *
	 * @return array{0: string, 1: int, 2: array<int, string|null>, 3: ParsedComment[]|null} Name, line, arguments and comments.
	 */
	public function close() {
		$arguments = [];

		for ( $i = 0; $i <= $this->argument_index; ++$i ) {
			$arguments[ $i ] = isset( $this->arguments[ $i ] ) ? $this->arguments[ $i ] : null;
		}

		return [
			$this->name,
			$this->line,
			$arguments,
			$this->comments,
		];
	}
}
