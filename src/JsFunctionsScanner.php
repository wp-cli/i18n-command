<?php

namespace WP_CLI\I18n;

use Gettext\Translations;
use Gettext\Utils\JsFunctionsScanner as GettextJsFunctionsScanner;
use Gettext\Utils\ParsedComment;
use Peast\Peast;
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
		$traverser->addFunction( function ( $node ) use ( &$translations, $options ) {
			$functions = $options['functions'];
			$file      = $options['file'];

			/* @var CallExpression $node */

			if ( 'CallExpression' !== $node->getType() ) {
				return;
			}

			/* @var Identifier $callee */
			$callee = $node->getCallee();

			if ( 'Identifier' !== $callee->getType() ) {
				return;
			}

			$function_name = $callee->getName();
			$line_found    = $node->getLocation()->getStart()->getLine();

			if ( ! isset( $functions[ $function_name ] ) ) {
				return;
			}

			$args = [];

			foreach ( $node->getArguments() as $argument ) {
				if ( 'Identifier' === $argument->getType() ) {
					$args[] = ''; // The value doesn't matter as it's unused.
				}

				if ( 'Literal' === $argument->getType() ) {
					/* @var Literal $argument */
					$args[] = $argument->getValue();
				}
			}

			$domain = $context = $original = $plural = null;

			switch ( $functions[ $function_name ] ) {
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
				$translation->addReference( $file, $line_found );

				if ( isset( $function[3] ) ) {
					foreach ( [] as $extractedComment ) {
						$translation->addExtractedComment( $extractedComment );
					}
				}

				/* @var \Peast\Syntax\Node\Comment $comment */
				foreach ( $node->getCallee()->getLeadingComments() as $comment ) {
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
