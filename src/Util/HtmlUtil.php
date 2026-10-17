<?php

namespace Vrok\SymfonyAddons\Util;

class HtmlUtil
{
    /**
     * The defaults of mb_trim() plus invisible characters that editors leave in empty fields.
     */
    private const string TRIM_CHARACTERS = " \f\n\r\t\v\x00\u{a0}\u{85}\u{1680}\u{180e}\u{2000}"
        ."\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200a}\u{2028}"
        ."\u{2029}\u{202f}\u{205f}\u{3000}\u{200b}\u{2060}\u{feff}\u{ad}";

    /**
     * Used as normalizer for the Length and NotBlank constraints, to validate the real text length
     * of HTML fields.
     */
    public static function stripHtml(string $html): string
    {
        // A tag starts with a letter, so "<3" stays. Unclosed comments and tags run to the end,
        // like in browsers, which keeps the regex linear.
        $text = mb_check_encoding($html, 'UTF-8')
            ? preg_replace(
                '/<!--[^-]*+(?:-(?!->)[^-]*+)*+(?:-->|\z)|<[!?\/]?[a-z][^>]*+(?:>|\z)/i',
                '',
                $html
            )
            : null;

        // unchanged, so Length reports the charset violation or counts the raw HTML
        if (null === $text) {
            return $html;
        }

        return mb_trim(
            html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'),
            self::TRIM_CHARACTERS
        );
    }
}
