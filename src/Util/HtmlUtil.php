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
        // returned unchanged, so the Length constraint still reports the charset violation
        if (!mb_check_encoding($html, 'UTF-8')) {
            return $html;
        }

        // libxml keeps text like "<3" that strip_tags() removes and parses in linear time, unlike
        // Dom\HTMLDocument, which needs hundreds of MB for some KB of nested formatting tags.
        // LIBXML_PARSEHUGE keeps the text below a nesting depth of 256, the encoding hint replaces
        // the default ISO-8859-1. The document's text includes text after a closing </html>.
        $document = new \DOMDocument();
        $document->loadHTML(
            '<?xml encoding="UTF-8"><body>'.$html.'</body>',
            \LIBXML_NOERROR | \LIBXML_NOWARNING | \LIBXML_NONET | \LIBXML_PARSEHUGE
        );

        // mb_trim also removes &nbsp; decoded to U+00A0, e.g. in "<p>&nbsp;</p>" of empty editors
        return mb_trim($document->textContent);
    }
}
