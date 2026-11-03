<?php

/**
 * This file is part of the yform/usability package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability\Search;

use rex_yform_manager_field;
use rex_yform_manager_query;

/**
 * Das Suchfeld: Jedes Wort muss in mindestens einem Feld als Teiltreffer vorkommen.
 */
final class QuickSearch
{
    /**
     * @param list<rex_yform_manager_field> $fields
     * @param list<string> $terms
     */
    public static function apply(rex_yform_manager_query $q, array $fields, array $terms, bool $withId = true): void
    {
        foreach ($terms as $term) {
            $matched = false;
            $alias = $q->getTableAlias();

            $q->whereNested(static function (rex_yform_manager_query $nested) use ($fields, $term, $withId, $alias, &$matched) {
                $nested->alias($alias);
                if ($withId && ctype_digit($term)) {
                    $p = FieldFilter::param();
                    $nested->whereRaw(FieldFilter::column($nested, 'id') . ' = :' . $p, [$p => (int) $term]);
                    $matched = true;
                }
                foreach ($fields as $field) {
                    $matched = self::applyField($nested, $field, $term) || $matched;
                }
            }, 'OR');

            // Ein Wort, das nirgends passen kann, darf das Ergebnis nicht erweitern
            if (!$matched) {
                $q->whereRaw('0 = 1');
            }
        }
    }

    private static function applyField(rex_yform_manager_query $q, rex_yform_manager_field $field, string $term): bool
    {
        $type = $field->getTypeName();
        $col = FieldFilter::column($q, $field->getName());

        switch ($type) {
            case 'choice':
                $values = [];
                foreach (FieldFilter::choices($field) as $value => $label) {
                    if (false !== mb_stripos($label, $term)) {
                        $values[] = (string) $value;
                    }
                }
                if (!$values) {
                    return false;
                }
                FieldFilter::apply($q, $field, 'in', $values);
                return true;

            case 'be_manager_relation':
                return FieldFilter::applyRelationTerm($q, $field, $term);

            case 'be_link':
            case 'be_linklist':
                FieldFilter::applyArticleTerm($q, $col, $term);
                return true;

            case 'be_media':
            case 'be_medialist':
                FieldFilter::apply($q, $field, 'name_contains', $term);
                return true;

            case 'integer':
            case 'number':
                if (!is_numeric($term)) {
                    return false;
                }
                FieldFilter::apply($q, $field, 'eq', $term);
                return true;
        }

        if (in_array('contains', Operators::forType($type, (string) $field->getDatabaseFieldType()), true)) {
            FieldFilter::apply($q, $field, 'contains', $term);
            return true;
        }

        return false;
    }
}
