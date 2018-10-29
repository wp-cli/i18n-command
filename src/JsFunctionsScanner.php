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
		// Temporary workaround for https://github.com/wp-cli/i18n-command/issues/98.
		$this->code = str_replace( '/"/', '/\"/', $this->code );
		$this->code = str_replace( '/"|\'/', '/\"|\\\'/', $this->code );

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
			if ( 'CallExpression' !== $node->getType() ) {
				return;
			}

			$callee = $this->resolveExpressionCallee( $node );

			if ( ! $callee || ! isset( $functions[ $callee['name'] ] ) ) {
				return;
			}

			/** @var Node\CallExpression $node */
			foreach ( $node->getArguments() as $argument ) {
				// Support nested function calls.
				$argument->setLeadingComments( $argument->getLeadingComments() + $node->getLeadingComments() );
			}

			foreach( $callee['comments'] as $comment ) {
				$all_comments[] = $comment;
			}

			$context = $plural = null;
			$args    = [];

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

			switch ( $functions[ $callee['name'] ] ) {
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
			}

			if ( (string) $original !== '' && ( $domain === $translations->getDomain() || null === $translations->getDomain() ) ) {
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

	/**
	 * Resolve the callee of a call expression using known formats.
	 *
	 * @param Node\CallExpression $node The call expression whose callee to resolve.
	 *
	 * @return array|bool Array containing the name and comments of the identifier if resolved. False if not.
	 */
	private function resolveExpressionCallee( Node\CallExpression $node ) {
		$callee = $node->getCallee();

		// If the callee is a simple identifier it can simply be returned.
		// For example: __( "translation" ).
		if ( 'Identifier' === $callee->getType() ) {
			return [
				'name'     => $callee->getName(),
				'comments' => $callee->getLeadingComments()
			];
		}

		// If the callee is a member expression resolve it to the property.
		// For example: wp.i18n.__( "translation" ) or u.__( "translation" ).
		if (
			'MemberExpression' === $callee->getType() &&
			'Identifier' === $callee->getProperty()->getType()
		) {
			return [
				'name'     => $callee->getProperty()->getName(),
				'comments' => $callee->getProperty()->getLeadingComments()
			];
		}

		// If the callee is a call expression as created by Webpack resolve it.
		// For example: Object(u.__)( "translation" ).
		if (
			'CallExpression' ===  $callee->getType() &&
			'Identifier' ===  $callee->getCallee()->getType() &&
			'Object' === $callee->getCallee()->getName() &&
			[] !== $callee->getArguments() &&
			'MemberExpression' === $callee->getArguments()[0]->getType()
		) {
			$property = $callee->getArguments()[0]->getProperty();

			// Matches minified webpack statements: Object(u.__)( "translation" ).
			if ( 'Identifier' === $property->getType() ) {
				return [
					'name'     => $property->getName(),
					'comments' => $property->getLeadingComments()
				];
			}

			// Matches unminified webpack statements:
			// Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__["__"])( "translation" );
			if ( 'Literal' === $property->getType() ) {
				return [
					'name'     => $property->getValue(),
					'comments' => $property->getLeadingComments()
				];
			}
		}

		// Unknown format.
		return false;
	}
}
