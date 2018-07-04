<?php

namespace WP_CLI\I18n;

use Gettext\Translations;
use Gettext\Utils\JsFunctionsScanner as GettextJsFunctionsScanner;
use Gettext\Utils\ParsedComment;
use Peast\Peast;
use Peast\Syntax\Node;
use Peast\Traverser;

final class JsFunctionsScanner extends GettextJsFunctionsScanner {
	/**
	 * If not false, comments will be extracted.
	 *
	 * @var string|false|array
	 */
	private $extractComments = false;

	/**
	 * Enable extracting comments that start with a tag (if $tag is empty all the comments will be extracted).
	 *
	 * @param mixed $tag
	 */
	public function enableCommentsExtraction( $tag = '' ) {
		$this->extractComments = $tag;
	}

	/**
	 * Disable comments extraction.
	 */
	public function disableCommentsExtraction() {
		$this->extractComments = false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function saveGettextFunctions( Translations $translations, array $options ) {
		$ast = Peast::latest( $this->code, [
			'sourceType' => Peast::SOURCE_TYPE_MODULE,
			'comments'   => false !== $this->extractComments,
			'jsx'        => true,
		] )->parse();

		$traverser = new Traverser();

		$all_comments = [];

		/**
		 * Traverse through JS code to find and extract gettext functions.
		 *
		 * Make sure translator comments in front of variable declarations
		 * and inside nested call expressions are available when parsing the function call.
		 */
		$traverser->addFunction( function ( $node ) use ( &$translations, $options, &$all_comments ) {
			$functions = $options['functions'];
			$file      = $options['file'];

			/** @var Node\Node $node */
			foreach( $node->getLeadingComments() as $comment ) {
				$all_comments[] = $comment;
			}

			/** @var Node\CallExpression $node */
			if ( 'CallExpression' !== $node->getType() || 'Identifier' !== $node->getCallee()->getType() ) {
				return;
			}

			/** @var Node\CallExpression $node */
			foreach ( $node->getArguments() as $argument ) {
				// Support nested function calls.
				$argument->setLeadingComments( $argument->getLeadingComments() + $node->getLeadingComments() );
			}

			/** @var Node\Identifier $callee */
			$callee = $node->getCallee();

			if ( ! isset( $functions[ $callee->getName() ] ) ) {
				return;
			}

			foreach( $callee->getLeadingComments() as $comment ) {
				$all_comments[] = $comment;
			}

			$domain = $context = $original = $plural = null;
			$args   = [];

			/** @var Node\Node $argument */
			foreach ( $node->getArguments() as $argument ) {
				foreach( $argument->getLeadingComments() as $comment ) {
					$all_comments[] = $comment;
				}

				if ( 'Identifier' === $argument->getType() ) {
					$args[] = ''; // The value doesn't matter as it's unused.
				}

				if ( 'Literal' === $argument->getType() ) {
					/** @var Node\Literal $argument */
					$args[] = $argument->getValue();
				}
			}

			switch ( $functions[ $callee->getName() ] ) {
				case 'text_domain':
				case 'gettext':
					if ( ! isset( $args[1] ) ) {
						break;
					}

					list( $original, $domain ) = $args;
					break;

				case 'text_context_domain':
					if ( ! isset( $args[2] ) ) {
						break;
					}

					list( $original, $context, $domain ) = $args;
					break;

				case 'single_plural_number_domain':
					if ( ! isset( $args[3] ) ) {
						break;
					}

					list( $original, $plural, $number, $domain ) = $args;
					break;

				case 'single_plural_number_context_domain':
					if ( ! isset( $args[4] ) ) {
						break;
					}

					list( $original, $plural, $number, $context, $domain ) = $args;
					break;
			}

			if ( (string) $original !== '' && ( $domain === null || $domain === $translations->getDomain() ) ) {
				$translation = $translations->insert( $context, $original, $plural );
				$translation->addReference( $file, $node->getLocation()->getStart()->getLine() );

				/** @var Node\Comment $comment */
				foreach ( $all_comments as $comment ) {
					if ( $node->getLocation()->getStart()->getLine() - $comment->getLocation()->getEnd()->getLine() > 1 ) {
						continue;
					}

					if ( $node->getLocation()->getStart()->getColumn() < $comment->getLocation()->getStart()->getColumn() ) {
						continue;
					}

					$parsed_comment = ParsedComment::create( $comment->getRawText(), $comment->getLocation()->getStart()->getLine() );
					$prefixes       = array_filter( (array) $this->extractComments );

					if ( $parsed_comment->checkPrefixes( $prefixes ) ) {
						$translation->addExtractedComment( $parsed_comment->getComment() );
					}
				}

				if ( isset( $parsed_comment ) ) {
					$all_comments = [];
				}
			}
		} );

		$traverser->traverse( $ast );
	}
}
