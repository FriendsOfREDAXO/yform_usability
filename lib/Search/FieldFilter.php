<?php

/**
 * This file is part of the yform/usability package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability\Search;

use DateTimeImmutable;
use rex;
use rex_addon;
use rex_i18n;
use rex_sql;
use rex_yform_choice_list;
use rex_yform_manager_field;
use rex_yform_manager_query;
use rex_yform_manager_table;
use Sprog\Wildcard;
use yform\usability\Extensions;

/**
 * Baut die SQL-Bedingungen für „+ Filter“ und den Zeitraum. Hilfen für Spalten,
 * Choice-Labels und Relationsziele nutzt auch QuickSearch.
 */
final class FieldFilter
{
    public const DATE_TYPES = ['date', 'datetime', 'datestamp'];

    private const NUMBER_TYPES = ['integer', 'number'];

    private static int $paramCounter = 0;

    /** @var array<string, array<string, string>> */
    private static array $choiceCache = [];

    /**
     * Gibt false zurück, wenn der Wert für die Bedingung ungültig ist und nichts angewendet wurde.
     *
     * @param string|list<string> $value
     */
    public static function apply(rex_yform_manager_query $q, rex_yform_manager_field $field, string $op, string|array $value): bool
    {
        $type = $field->getTypeName();
        $col = self::column($q, $field->getName());

        switch ($op) {
            case 'contains':
                $p = self::param();
                $q->whereRaw(Terms::normalizedColumn($col) . ' LIKE :' . $p, [$p => Terms::likeContains(self::plain((string) $value))]);
                return true;
            case 'not_contains':
                $p = self::param();
                $q->whereRaw('(' . $col . ' IS NULL OR ' . Terms::normalizedColumn($col) . ' NOT LIKE :' . $p . ')', [$p => Terms::likeContains(self::plain((string) $value))]);
                return true;
            case 'equals':
                $p = self::param();
                $q->whereRaw(Terms::normalizedColumn($col) . ' = :' . $p, [$p => self::plain((string) $value)]);
                return true;
            case 'empty':
            case 'not_set':
                return self::applyEmpty($q, $field, $col, true);
            case 'not_empty':
            case 'set':
                return self::applyEmpty($q, $field, $col, false);
            case 'checked':
                $q->whereRaw($col . ' = 1');
                return true;
            case 'unchecked':
                $q->whereRaw('(' . $col . ' = 0 OR ' . $col . " = '' OR " . $col . ' IS NULL)');
                return true;
            case 'eq':
            case 'neq':
            case 'gt':
            case 'lt':
                if (!is_numeric($value)) {
                    return false;
                }
                $p = self::param();
                $operator = ['eq' => '=', 'neq' => '<>', 'gt' => '>', 'lt' => '<'][$op];
                $sql = $col . ' ' . $operator . ' :' . $p;
                if ('neq' === $op) {
                    $sql = '(' . $sql . ' OR ' . $col . ' IS NULL)';
                }
                $q->whereRaw($sql, [$p => $value + 0]);
                return true;
            case 'between':
                $value = array_pad((array) $value, 2, '');
                if (in_array($type, self::DATE_TYPES, true)) {
                    $range = DateRange::resolve('custom', $value[0], $value[1], new DateTimeImmutable());
                    if (!$range || null !== $range->error) {
                        return false;
                    }
                    self::applyDate($q, $field, $range);
                    return true;
                }
                $bounds = array_filter(['>=' => $value[0], '<=' => $value[1]], static fn ($v) => '' !== trim((string) $v));
                if (!$bounds || array_filter($bounds, static fn ($v) => !is_numeric($v))) {
                    return false;
                }
                if (2 === count($bounds) && $bounds['>='] > $bounds['<=']) {
                    return false;
                }
                foreach ($bounds as $operator => $bound) {
                    $p = self::param();
                    $q->whereRaw($col . ' ' . $operator . ' :' . $p, [$p => $bound + 0]);
                }
                return true;
            case 'before':
            case 'after':
                $date = DateRange::parseDate((string) $value);
                if (!$date) {
                    return false;
                }
                $p = self::param();
                $sql = 'before' === $op ? $col . ' < :' . $p : $col . ' >= :' . $p;
                $bound = 'before' === $op ? $date : $date->modify('+1 day');
                $q->whereRaw($sql, [$p => $bound->format('Y-m-d 00:00:00')]);
                return true;
            case 'in':
            case 'not_in':
                if ('be_manager_relation' === $type) {
                    return self::applyRelationIn($q, $field, (array) $value);
                }
                return self::applyChoiceIn($q, $field, $col, (array) $value, 'not_in' === $op);
            case 'name_contains':
                if (in_array($type, ['be_link', 'be_linklist'], true)) {
                    self::applyArticleTerm($q, $col, (string) $value);
                    return true;
                }
                $p = self::param();
                $q->whereRaw($col . ' LIKE :' . $p, [$p => Terms::likeContains(self::plain((string) $value))]);
                return true;
        }

        return false;
    }

    public static function applyDate(rex_yform_manager_query $q, rex_yform_manager_field $field, DateRange $range): void
    {
        $col = self::column($q, $field->getName());
        if (null !== $range->sqlFrom()) {
            $p = self::param();
            $q->whereRaw($col . ' >= :' . $p, [$p => $range->sqlFrom()]);
        }
        if (null !== $range->sqlToExclusive()) {
            $p = self::param();
            $q->whereRaw($col . ' < :' . $p, [$p => $range->sqlToExclusive()]);
        }
    }

    /**
     * Wert → übersetztes Label (unescaped).
     *
     * @return array<string, string>
     */
    public static function choices(rex_yform_manager_field $field): array
    {
        $key = $field->getElement('table_name') . '.' . $field->getName();
        if (isset(self::$choiceCache[$key])) {
            return self::$choiceCache[$key];
        }

        $list = new rex_yform_choice_list(['group_by' => $field->getElement('group_by') ?: null]);
        $choices = $field->getElement('choices');
        if (is_string($choices) && 'SELECT' === rex_sql::getQueryType($choices)) {
            $list->createListFromSqlArray(rex_sql::factory()->getArray($choices));
        } elseif (is_string($choices) && str_starts_with(trim($choices), '{')) {
            $list->createListFromJson($choices);
        } else {
            $list->createListFromStringArray(Extensions::getArrayFromString($choices));
        }

        $sprog = rex_addon::get('sprog')->isAvailable();
        $result = [];
        foreach ($list->getChoicesByValues() as $value => $label) {
            $label = rex_i18n::translate((string) $label, false);
            if ($sprog) {
                $label = Wildcard::parse($label);
            }
            $result[(string) $value] = $label;
        }

        return self::$choiceCache[$key] = $result;
    }

    /**
     * ID → Anzeigetext der Zieltabelle (unescaped), oder null, wenn sie mehr als $limit Zeilen hat.
     *
     * @return array<int, string>|null
     */
    public static function relationOptions(rex_yform_manager_field $field, int $limit = 1000): ?array
    {
        $columns = self::relationDisplayColumns($field);
        if (!$columns) {
            return [];
        }

        $sql = rex_sql::factory();
        $table = $sql->escapeIdentifier((string) $field->getElement('table'));
        if ((int) $sql->getArray('SELECT COUNT(*) AS c FROM ' . $table)[0]['c'] > $limit) {
            return null;
        }

        $select = implode(', ', array_map([$sql, 'escapeIdentifier'], $columns));
        $options = [];
        foreach ($sql->getArray('SELECT id, ' . $select . ' FROM ' . $table . ' ORDER BY ' . $sql->escapeIdentifier($columns[0])) as $row) {
            $id = (int) $row['id'];
            unset($row['id']);
            $options[$id] = trim(implode(' ', array_filter(array_map('strval', $row), 'strlen'))) ?: (string) $id;
        }
        return $options;
    }

    /**
     * Relation, deren verknüpfter Datensatz das Wort im Anzeigetext enthält – als Unterabfrage,
     * damit auch große Zieltabellen keine riesigen ID-Listen erzeugen.
     */
    public static function applyRelationTerm(rex_yform_manager_query $q, rex_yform_manager_field $field, string $term): bool
    {
        $columns = self::relationDisplayColumns($field);
        if (!$columns) {
            return false;
        }

        $sql = rex_sql::factory();
        $p = self::param();
        $match = [];
        foreach ($columns as $column) {
            $match[] = Terms::normalizedColumn('`yfu_r`.' . $sql->escapeIdentifier($column)) . ' LIKE :' . $p;
        }
        $match = '(' . implode(' OR ', $match) . ')';
        $target = $sql->escapeIdentifier((string) $field->getElement('table'));
        $alias = $sql->escapeIdentifier($q->getTableAlias());

        if ($field->getElement('relation_table')) {
            $relation = self::relationTableColumns($field);
            if (!$relation) {
                return false;
            }
            $exists = sprintf(
                'EXISTS (SELECT 1 FROM %s AS `yfu_x` JOIN %s AS `yfu_r` ON `yfu_r`.`id` = `yfu_x`.%s WHERE `yfu_x`.%s = %s.`id` AND %s)',
                $sql->escapeIdentifier((string) $field->getElement('relation_table')),
                $target,
                $sql->escapeIdentifier($relation['target']),
                $sql->escapeIdentifier($relation['source']),
                $alias,
                $match,
            );
        } else {
            $exists = sprintf(
                'EXISTS (SELECT 1 FROM %s AS `yfu_r` WHERE FIND_IN_SET(`yfu_r`.`id`, %s) AND %s)',
                $target,
                self::column($q, $field->getName()),
                $match,
            );
        }

        $q->whereRaw($exists, [$p => Terms::likeContains(self::plain($term))]);
        return true;
    }

    /**
     * Link-Feld, dessen Artikel (einer der Artikel) das Wort im Namen trägt.
     */
    public static function applyArticleTerm(rex_yform_manager_query $q, string $col, string $term): void
    {
        $p = self::param();
        $q->whereRaw(
            'EXISTS (SELECT 1 FROM ' . rex_sql::factory()->escapeIdentifier(rex::getTable('article')) . ' AS `yfu_a` WHERE FIND_IN_SET(`yfu_a`.`id`, ' . $col . ') AND `yfu_a`.`name` LIKE :' . $p . ')',
            [$p => Terms::likeContains(self::plain($term))],
        );
    }

    /**
     * Relation per YForm-Suchlogik (Mehrfachwerte und Relationstabelle), Treffer ODER-verknüpft.
     *
     * @param list<int> $ids
     */
    public static function applyRelationIds(rex_yform_manager_query $q, rex_yform_manager_field $field, array $ids): void
    {
        if (!$ids) {
            $q->whereRaw('0 = 1');
            return;
        }
        $alias = $q->getTableAlias();
        $q->whereNested(static function (rex_yform_manager_query $nested) use ($field, $ids, $alias) {
            $nested->alias($alias);
            \rex_yform_value_be_manager_relation::getSearchFilter(['field' => $field, 'value' => $ids, 'query' => $nested]);
        });
    }

    public static function column(rex_yform_manager_query $q, string $name): string
    {
        $sql = rex_sql::factory();
        return $sql->escapeIdentifier($q->getTableAlias()) . '.' . $sql->escapeIdentifier($name);
    }

    public static function param(): string
    {
        return 'yfu' . ++self::$paramCounter;
    }

    /** Eingabe ohne weiche Trennzeichen, passend zu Terms::normalizedColumn(). */
    public static function plain(string $value): string
    {
        return trim(str_replace(["\u{00AD}", '&shy;'], '', $value));
    }

    private static function applyEmpty(rex_yform_manager_query $q, rex_yform_manager_field $field, string $col, bool $empty): bool
    {
        $type = $field->getTypeName();
        if ('be_manager_relation' === $type && $field->getElement('relation_table')) {
            // Leer heißt hier: kein Eintrag in der Relationstabelle
            $relation = self::relationTableColumns($field);
            if (!$relation) {
                return false;
            }
            $sql = rex_sql::factory();
            $exists = sprintf(
                'EXISTS (SELECT 1 FROM %1$s WHERE %1$s.%2$s = %3$s.`id`)',
                $sql->escapeIdentifier((string) $field->getElement('relation_table')),
                $sql->escapeIdentifier($relation['source']),
                $sql->escapeIdentifier($q->getTableAlias()),
            );
            $q->whereRaw($empty ? 'NOT ' . $exists : $exists);
            return true;
        }

        if (in_array($type, self::NUMBER_TYPES, true)) {
            $condition = $col . ' IS NULL';
        } elseif (in_array($type, self::DATE_TYPES, true)) {
            $condition = '(' . $col . " IS NULL OR CAST(" . $col . " AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00'))";
        } else {
            $condition = '(' . $col . " = '' OR " . $col . ' IS NULL)';
        }
        $q->whereRaw($empty ? $condition : 'NOT ' . $condition);
        return true;
    }

    /**
     * @param list<string> $values
     */
    private static function applyChoiceIn(rex_yform_manager_query $q, rex_yform_manager_field $field, string $col, array $values, bool $negate): bool
    {
        $values = array_values(array_intersect($values, array_map('strval', array_keys(self::choices($field)))));
        if (!$values) {
            return false;
        }

        $parts = [];
        $params = [];
        foreach ($values as $value) {
            $p = self::param();
            $params[$p] = $value;
            $parts[] = 1 == $field->getElement('multiple') ? 'FIND_IN_SET(:' . $p . ', ' . $col . ')' : $col . ' = :' . $p;
        }
        $condition = '(' . implode(' OR ', $parts) . ')';
        if ($negate) {
            $condition = '(' . $col . ' IS NULL OR NOT ' . $condition . ')';
        }
        $q->whereRaw($condition, $params);
        return true;
    }

    /**
     * @param list<string> $values IDs oder, bei großen Zieltabellen, Suchtext
     */
    private static function applyRelationIn(rex_yform_manager_query $q, rex_yform_manager_field $field, array $values): bool
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($values, 'ctype_digit'))));
        $terms = array_values(array_filter(array_map([self::class, 'plain'], array_filter($values, static fn ($v) => !ctype_digit($v))), 'strlen'));
        if (!$ids && !$terms) {
            return false;
        }

        $alias = $q->getTableAlias();
        $q->whereNested(static function (rex_yform_manager_query $nested) use ($field, $ids, $terms, $alias) {
            $nested->alias($alias);
            if ($ids) {
                self::applyRelationIds($nested, $field, $ids);
            }
            foreach ($terms as $term) {
                self::applyRelationTerm($nested, $field, $term);
            }
        }, 'OR');
        return true;
    }

    /**
     * Quell- und Zielspalte der Relationstabelle, wie YForm sie ermittelt.
     *
     * @return array{source: string, target: string}|null
     */
    private static function relationTableColumns(rex_yform_manager_field $field): ?array
    {
        $relationTable = rex_yform_manager_table::get((string) $field->getElement('relation_table'));
        if (!$relationTable) {
            return null;
        }
        $source = $relationTable->getRelationsTo((string) $field->getElement('table_name'));
        $target = $relationTable->getRelationsTo((string) $field->getElement('table'));
        if (!$source || !$target) {
            return null;
        }
        $sourceName = reset($source)->getName();
        $targetName = reset($target)->getName();
        if ($sourceName === $targetName) {
            $targetName = next($target) ? current($target)->getName() : null;
        }
        return $targetName ? ['source' => $sourceName, 'target' => $targetName] : null;
    }

    /**
     * Anzeigefelder der Relation, nur echte Spalten der Zieltabelle.
     *
     * @return list<string>
     */
    private static function relationDisplayColumns(rex_yform_manager_field $field): array
    {
        $target = rex_yform_manager_table::get((string) $field->getElement('table'));
        if (!$target) {
            return [];
        }
        $existing = array_keys($target->getColumns());

        $columns = [];
        foreach (explode(',', (string) $field->getElement('field')) as $column) {
            $column = trim(trim($column), '"\'`');
            if (in_array($column, $existing, true)) {
                $columns[] = $column;
            }
        }
        return $columns;
    }
}
