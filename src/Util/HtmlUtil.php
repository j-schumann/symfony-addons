<?php

namespace Vrok\SymfonyAddons\Util;

class HtmlUtil
{
    /**
     * Returns the text content of the given HTML: strips the tags, decodes the entities and trims
     * the result. Used as normalizer for the Length and NotBlank constraints, to validate the real
     * text length of HTML fields.
     */
    public static function stripHtml(string $html): string
    {
        // strip_tags keeps entities like &nbsp; which would count as multiple characters
        return trim(html_entity_decode(strip_tags($html)));
    }
}
