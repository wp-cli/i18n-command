<?php

namespace WP_CLI\I18n;

use Gettext\Translations;
use eftec\bladeone\BladeOne;

// Modified Gettext Blade extractor that uses the up-to-date BladeOne standalone Blade engine

/**
 * Class to get gettext strings from blade.php files returning arrays.
 */
class BladeGettextExtractor extends \Gettext\Extractors\Extractor implements \Gettext\Extractors\ExtractorInterface
{
    /**
     * {@inheritdoc}
     */
    public static function fromString($string, Translations $translations, array $options = [])
    {
        if (empty($options['facade'])) {
            $cachePath = empty($options['cachePath']) ? sys_get_temp_dir() : $options['cachePath'];
            $bladeCompiler = new BladeOne(null, $cachePath);

            if (method_exists($bladeCompiler, 'withoutComponentTags')) {
                $bladeCompiler->withoutComponentTags();
            }

            $string = $bladeCompiler->compileString($string);
        } else {
            $string = $options['facade']::compileString($string);
        }

        \Gettext\Extractors\PhpCode::fromString($string, $translations, $options);
    }
}
