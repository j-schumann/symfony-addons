<?php

namespace Vrok\SymfonyAddons\Util;

class HtmlUtil
{
    /**
     * The default characters of mb_trim() plus the invisible characters editors leave in fields
     * that look empty: zero width space, word joiner, byte order mark and soft hyphen.
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
        // A tag starts with a letter, so text like "<3" is kept. Unclosed comments and tags end
        // at the end of the input, like in browsers, which also keeps the regex linear.
        $text = mb_check_encoding($html, 'UTF-8')
            ? preg_replace(
                '/<!--[^-]*+(?:-(?!->)[^-]*+)*+(?:-->|\z)|<[!?\/]?[a-z][^>]*+(?:>|\z)/i',
                '',
                $html
            )
            : null;

        // invalid UTF-8 and PCRE errors return the HTML unchanged, so the Length constraint
        // reports the charset violation or counts the full input
        if (null === $text) {
            return $html;
        }

        return mb_trim(
            html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'),
            self::TRIM_CHARACTERS
        );
    }
}
