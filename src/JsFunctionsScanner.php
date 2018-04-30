<?php

namespace WP_CLI\I18n;

use Gettext\Translations;
use Gettext\Utils\JsFunctionsScanner as GettextJsFunctionsScanner;
use Gettext\Utils\ParsedComment;
use Peast\Peast;
use Peast\Syntax\Node\Node;
use Peast\Syntax\Node\VariableDeclaration;
use Peast\Traverser;
use Peast\Syntax\Node\CallExpression;
use Peast\Syntax\Node\Identifier;
use Peast\Syntax\Node\Literal;

class JsFunctionsScanner extends GettextJsFunctionsScanner {
	/**
	 * If not false, comments will be extracted.
	 *
	 * @var string|false|array
	 */
	protected $extractComments = false;

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
			'sourceType' => Peast::SOURCE_TYPE_SCRIPT,
			'comments'   => false !== $this->extractComments,
		] )->parse();

		$traverser = new Traverser();

		/**
		 * Traverse through JS code to find and extract gettext functions.
		 *
		 * Make sure translator comments in front of variable declarations
		 * and inside nested call expressions are available when parsing the function call.
		 */
		$traverser->addFunction( function ( $node ) use ( &$translations, $options ) {
			$functions = $options['functions'];
			$file      = $options['file'];

			/* @var Node $node */

			if ( 'VariableDeclaration' === $node->getType() ) {
				/* @var VariableDeclaration $node */
				foreach ( $node->getDeclarations() as $declarator ) {
					$declarator->getInit()->setLeadingComments(
						$declarator->getInit()->getLeadingComments() + $node->getLeadingComments()
					);

					if ( 'CallExpression' === $declarator->getInit()->getType() && 'Identifier' === $declarator->getInit()->getCallee()->getType() ) {
						$declarator->getInit()->getCallee()->setLeadingComments(
							$declarator->getInit()->getCallee()->getLeadingComments() + $node->getLeadingComments()
						);
					}
				}
			}

			if ( 'CallExpression' !== $node->getType() ) {
				return;
			}

			/* @var CallExpression $node */
			foreach ( $node->getArguments() as $argument ) {
				// Support nested function calls.
				$argument->setLeadingComments( $argument->getLeadingComments() + $node->getLeadingComments() );
			}

			/* @var Identifier $callee */
			$callee = $node->getCallee();

			while ( 'MemberExpression' === $callee->getType() ) {
				$callee = $callee->getObject();
			}

			if ( ! isset( $functions[ $callee->getName() ] ) ) {
				return;
			}

			$domain = $context = $original = $plural = null;
			$args   = [];

			$comments = $node->getLeadingComments() + $callee->getLeadingComments();

			/* @var Node $argument */
			foreach ( $node->getArguments() as $argument ) {
				$comments = array_merge( $comments, $argument->getLeadingComments() );

				if ( 'Identifier' === $argument->getType() ) {
					$args[] = ''; // The value doesn't matter as it's unused.
				}

				if ( 'Literal' === $argument->getType() ) {
					/* @var Literal $argument */
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

			// Todo: Require a domain?
			if ( (string) $original !== '' && ( $domain === null || $domain === $translations->getDomain() ) ) {
				$translation = $translations->insert( $context, $original, $plural );
				$translation->addReference( $file, $node->getLocation()->getStart()->getLine() );

				/* @var \Peast\Syntax\Node\Comment $comment */
				foreach ( $comments as $comment ) {
					$parsed_comment = ParsedComment::create( $comment->getRawText(), $comment->getLocation()->getStart()->getLine() );
					$prefixes       = array_filter( (array) $this->extractComments );

					if ( $parsed_comment->checkPrefixes( $prefixes ) ) {
						$translation->addExtractedComment( $parsed_comment->getComment() );
					}
				}
			}
		} );

		$traverser->traverse( $ast );
	}
}
