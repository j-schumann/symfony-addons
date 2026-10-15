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
        // the HTML5 parser keeps text like "<3" that strip_tags() would remove, mb_trim also
        // removes &nbsp; decoded to U+00A0, e.g. in "<p>&nbsp;</p>" of empty editors
        $document = \Dom\HTMLDocument::createFromString($html, \LIBXML_NOERROR, 'UTF-8');

        return mb_trim($document->body->textContent ?? '');
    }
}
