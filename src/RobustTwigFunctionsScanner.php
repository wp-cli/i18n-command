<?php

namespace WP_CLI\I18n;

use Gettext\Utils\FunctionsScanner;

/**
 * This is a function scanner for Twig templates. The initial string (whether template text or filename) is parsed.
 * Then getFunctions() recursively traverses nodes.
 * Nodes expressing a function call to one of the WordPress i18n functions (see parent {$this->functions) and saveGettextFunctions())
 * are extracted.
 */
final class RobustTwigFunctionsScanner extends PhpFunctionsScanner {
    public function __construct( $string, $twig, $func_names ) {
        $this->tokens = $twig->parse( $twig->tokenize( new \Twig_Source( $string, '' ) ) );
        $this->functions = $func_names;
    }

    /**
     * A pseudo-generator to extract twig nodes corresponding to i18n function calls.
     * @param array $constants Unused yet.
     * @return array List of functions arguments/line-number compatible with PhpFunctionsScanner.
     */
    public function getFunctions( array $constants = [] ) {
        return self::_get_gettext_functions( $this->tokens );
    }

    private function is_gettext_function( $obj ) {
        return ( $obj instanceof \Twig_Node_Expression_Function && in_array( $obj->getAttribute( 'name' ), $this->functions, TRUE ) );
    }

    private function _get_gettext_functions( $tokens ) {
        if ( is_array( $tokens ) ) {
            $functions = [];
            foreach($tokens as $v) {
                $functions = array_merge( $functions, self::_get_gettext_functions( $v ) );
            }
            return $functions;
        }

        $value = $tokens;
        if ( $this->is_gettext_function( $value )) {
            $arguments_obj = (array)$value->getNode( 'arguments' )->getIterator();
            $name = $value->getAttribute('name');
            $line = $value->getTemplateLine();

            // basic verification of node arguments
            if ( ! ( $arguments_obj[0] instanceof \Twig_Node_Expression_Constant && $arguments_obj[1] instanceof \Twig_Node_Expression_Constant ) ) {
                \WP_CLI::warning( "Translation expression does not contains constant expressions " . PHP_EOL );
                printf( STDERR, print_r( $arguments_obj, TRUE ) );
                return [];
            }

            $arguments = array_map( function( $obj ) use( $name ) {
                if ($name == '_n' && $obj instanceof \Twig_Node_Expression_GetAttr) {
                    return "count";
                } else {
                    return $obj->getAttribute('value');
                }
            }, $arguments_obj );

            return [ [ $name, $line, $arguments ] ];
        }

        $functions = [];
        foreach( $tokens->getIterator() as $v) {
            $functions = array_merge( $functions, self::_get_gettext_functions( $v ) );
        }
        return $functions;
    }
}
