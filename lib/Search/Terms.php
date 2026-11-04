<?php

/**
 * This file is part of the yform/usability package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability\Search;

/**
 * Zerlegt die Eingabe des Suchfelds in Wörter und bereitet sie für LIKE auf.
 */
final class Terms
{
    public const MAX_TERMS = 10;

    /** Hinter jedes LIKE mit likeContains(): eigenes Zeichen, weil der Backslash vom SQL-Modus abhängt (NO_BACKSLASH_ESCAPES) */
    public const LIKE_ESCAPE = " ESCAPE '!'";

    private const SOFT_HYPHENS = ["\u{00AD}", '&shy;'];

    /**
     * @return list<string>
     */
    public static function split(string $input): array
    {
        $input = str_replace(self::SOFT_HYPHENS, '', $input);

        preg_match_all('/"([^"]*)"|(\S+)/u', $input, $matches, PREG_SET_ORDER);

        $terms = [];
        foreach ($matches as $match) {
            $term = '' !== $match[1] ? $match[1] : ($match[2] ?? '');
            $term = trim(str_replace(['*', '"'], '', $term));
            if ('' !== $term) {
                $terms[] = $term;
            }
        }

        return array_slice($terms, 0, self::MAX_TERMS);
    }

    public static function escapeLike(string $value): string
    {
        return strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }

    public static function likeContains(string $term): string
    {
        return '%' . self::escapeLike($term) . '%';
    }

    /**
     * SQL-Ausdruck für eine Spalte ohne weiche Trennzeichen.
     */
    public static function normalizedColumn(string $quotedColumn): string
    {
        return "REPLACE(REPLACE(" . $quotedColumn . ", UNHEX('C2AD'), ''), '&shy;', '')";
    }
}
