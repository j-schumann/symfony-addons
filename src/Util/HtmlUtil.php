<?php

namespace Vrok\SymfonyAddons\Util;

class HtmlUtil
{
    /**
     * Used as normalizer for the Length and NotBlank constraints, to validate the real text length
     * of HTML fields.
     */
    public static function stripHtml(string $html): string
    {
        // mb_trim also removes &nbsp; decoded to U+00A0, e.g. in "<p>&nbsp;</p>" of empty editors
        return mb_trim(
            html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5)
        );
    }
}
