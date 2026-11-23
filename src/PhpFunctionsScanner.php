<?php

namespace WP_CLI\I18n;

use Gettext\Scanner\ParsedFunction;
use Gettext\Scanner\PhpFunctionsScanner as GettextPhpFunctionsScanner;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NodeTraverser;

/**
 * Finds function calls and their arguments in PHP code.
 *
 * Unlike the scanner of gettext/php-scanner, this one recovers from syntax errors
 * instead of skipping the whole file.
 */
class PhpFunctionsScanner extends GettextPhpFunctionsScanner {

	/**
	 * {@inheritdoc}
	 *
	 * @return ParsedFunction[]
	 */
	public function scan( string $code, string $filename ): array {
		$ast = $this->parser->parse( $code, new Collecting() );

		if ( empty( $ast ) ) {
			return [];
		}

		$visitor = $this->createNodeVisitor( $filename );

		$traverser = new NodeTraverser();
		$traverser->addVisitor( $visitor );
		$traverser->traverse( $ast );

		return $visitor->getFunctions();
	}
}
