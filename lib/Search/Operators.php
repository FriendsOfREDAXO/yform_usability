<?php

/**
 * This file is part of the yform/usability package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability\Search;

/**
 * Welche Bedingungen „+ Filter“ je YForm-Feldtyp anbietet.
 */
final class Operators
{
    public const TEXT = ['contains', 'equals', 'not_contains', 'empty', 'not_empty'];

    private const BY_TYPE = [
        'text' => self::TEXT,
        'textarea' => self::TEXT,
        'email' => self::TEXT,
        'url' => self::TEXT,
        'ip' => self::TEXT,
        'uuid' => self::TEXT,
        'showvalue' => self::TEXT,
        'hashvalue' => self::TEXT,
        'emptyname' => self::TEXT,
        'upload' => self::TEXT,
        'integer' => ['eq', 'neq', 'gt', 'lt', 'between', 'empty'],
        'number' => ['eq', 'neq', 'gt', 'lt', 'between', 'empty'],
        'date' => ['between', 'before', 'after', 'empty'],
        'datetime' => ['between', 'before', 'after', 'empty'],
        'datestamp' => ['between', 'before', 'after', 'empty'],
        'time' => ['contains', 'equals', 'empty', 'not_empty'],
        'choice' => ['in', 'not_in', 'empty'],
        'checkbox' => ['checked', 'unchecked'],
        'be_manager_relation' => ['in', 'empty'],
        'be_media' => ['set', 'not_set', 'name_contains'],
        'be_medialist' => ['set', 'not_set', 'name_contains'],
        'be_link' => ['set', 'not_set', 'name_contains'],
        'be_linklist' => ['set', 'not_set', 'name_contains'],
        // ausdrücklich ohne Filter
        'prio' => [],
        'be_table' => [],
    ];

    private const LIST_VALUE = ['in', 'not_in', 'between'];

    private const NO_VALUE = ['empty', 'not_empty', 'checked', 'unchecked', 'set', 'not_set'];

    /**
     * @param mixed $attributes Feld-Attribute (JSON-String oder Array), für Textfelder mit type=date
     * @return list<string>
     */
    public static function forType(string $typeName, string $dbType, mixed $attributes = null): array
    {
        if (self::isTextDate($typeName, $attributes)) {
            return self::BY_TYPE['date'];
        }
        if (array_key_exists($typeName, self::BY_TYPE)) {
            return self::BY_TYPE[$typeName];
        }

        // Unbekannte Feldtypen (z. B. aus anderen Addons): Textspalten wie Text behandeln
        if (preg_match('/^(varchar|text|mediumtext|longtext)\b/i', trim($dbType))) {
            return self::TEXT;
        }

        return [];
    }

    /**
     * Textfeld, das per Attribut als HTML-Datumsfeld ausgezeichnet ist ({"type":"date"} oder datetime-local).
     * Projekte nutzen das statt des YForm-Typs date, der leere Werte als 0000-00-00 speichert.
     */
    public static function isTextDate(string $typeName, mixed $attributes): bool
    {
        if ('text' !== $typeName) {
            return false;
        }
        if (is_string($attributes)) {
            $attributes = json_decode($attributes, true);
        }
        $type = is_array($attributes) && is_string($attributes['type'] ?? null) ? strtolower($attributes['type']) : '';
        return in_array($type, ['date', 'datetime-local'], true);
    }

    public static function takesList(string $op): bool
    {
        return in_array($op, self::LIST_VALUE, true);
    }

    public static function takesNoValue(string $op): bool
    {
        return in_array($op, self::NO_VALUE, true);
    }
}
