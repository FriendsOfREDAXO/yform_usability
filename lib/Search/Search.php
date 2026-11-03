<?php

/**
 * This file is part of the yform/usability package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability\Search;

use DateTimeImmutable;
use rex_i18n;
use rex_yform_manager_field;
use rex_yform_manager_query;
use rex_yform_manager_table;
use yform\usability\Usability;

/**
 * Suche einer Tabelle: welche Felder wofür gelten, Zustand aus der URL, Anwenden auf die Liste.
 */
final class Search
{
    private ?State $state = null;

    private function __construct(public readonly rex_yform_manager_table $table)
    {
    }

    public static function forTable(rex_yform_manager_table $table): ?self
    {
        if (!$table->isSearchable() || !self::isEnabledFor($table->getTableName())) {
            return null;
        }
        return new self($table);
    }

    public static function isEnabledFor(string $tableName): bool
    {
        $config = Usability::getConfig();
        return '|1|' === ($config['use_inline_search'] ?? '')
            || in_array($tableName, explode('|', trim((string) ($config['search_tables'] ?? ''), '|')), true);
    }

    /**
     * Felder für das Suchfeld: alle durchsuchbaren, optional per Einstellung eingegrenzt.
     *
     * @return list<rex_yform_manager_field>
     */
    public function quickFields(): array
    {
        $fields = $this->searchableFields();
        $only = self::pipeList(Usability::getConfig('search_fields_' . $this->table->getTableName()));
        if ($only) {
            $fields = array_values(array_filter($fields, static fn ($f) => in_array($f->getName(), $only, true)));
        }
        return $fields;
    }

    /**
     * Durchsuchbare Datumsfelder, das eingestellte zuerst.
     *
     * @return list<rex_yform_manager_field>
     */
    public function dateFields(): array
    {
        $fields = array_values(array_filter($this->searchableFields(), static fn ($f) => in_array($f->getTypeName(), FieldFilter::DATE_TYPES, true)));
        $preferred = trim((string) Usability::getConfig('search_datefield_' . $this->table->getTableName()), '|');
        usort($fields, static fn ($a, $b) => ($b->getName() === $preferred) <=> ($a->getName() === $preferred));
        return $fields;
    }

    /**
     * @return array<string, array{field: rex_yform_manager_field, ops: list<string>}>
     */
    public function filterFields(): array
    {
        $result = ['id' => ['field' => self::idField($this->table->getTableName()), 'ops' => ['eq', 'gt', 'lt', 'between']]];
        foreach ($this->searchableFields() as $field) {
            $ops = Operators::forType($field->getTypeName(), (string) $field->getDatabaseFieldType());
            if ($ops) {
                $result[$field->getName()] = ['field' => $field, 'ops' => $ops];
            }
        }
        return $result;
    }

    /**
     * @return array{date: list<string>, filter: array<string, list<string>>}
     */
    public function allowed(): array
    {
        return [
            'date' => array_map(static fn ($f) => $f->getName(), $this->dateFields()),
            'filter' => array_map(static fn ($f) => $f['ops'], $this->filterFields()),
        ];
    }

    public function state(): State
    {
        return $this->state ??= State::fromRequest(self::request(), $this->allowed());
    }

    /**
     * GET plus Suche/Manager-Parameter aus POST (Massenbearbeitung und Speichern senden ohne Query-String).
     *
     * @return array<mixed>
     */
    public static function request(): array
    {
        return State::requestParams($_GET, $_POST);
    }

    /**
     * Wendet den Zustand an. Gibt die Meldungen zu Teilen zurück, die wegen ungültiger Werte nicht angewendet wurden.
     *
     * @return list<string>
     */
    public function apply(rex_yform_manager_query $query): array
    {
        $state = $this->state();
        $errors = [];

        $terms = Terms::split($state->q);
        if ($terms) {
            QuickSearch::apply($query, $this->quickFields(), $terms);
        }

        if (null !== $state->date) {
            $range = DateRange::resolve($state->date['preset'], $state->date['from'], $state->date['to'], new DateTimeImmutable());
            if ($range && null !== $range->error) {
                $errors[] = rex_i18n::rawMsg('yform_usability.search_error_' . $range->error);
            } elseif ($range) {
                FieldFilter::applyDate($query, $this->table->getValueField($state->date['field']), $range);
            }
        }

        $filterFields = $this->filterFields();
        foreach ($state->filters as $filter) {
            $field = $filterFields[$filter['field']]['field'];
            if (!FieldFilter::apply($query, $field, $filter['op'], $filter['value'])) {
                $errors[] = rex_i18n::rawMsg('yform_usability.search_error_invalid_filter', self::label($field));
            }
        }

        return $errors;
    }

    /**
     * Felder aktiver Filter und des Zeitraums, die in der Übersicht ausgeblendet sind, für diese Anfrage einblenden.
     * Wirkt nur im Speicher (die Feldobjekte der Tabelle), nichts wird gespeichert.
     *
     * @return list<string> Namen der eingeblendeten Felder
     */
    public function revealHiddenFields(): array
    {
        $state = $this->state();
        $names = array_column($state->filters, 'field');
        if (null !== $state->date) {
            $names[] = $state->date['field'];
        }

        $revealed = [];
        foreach (array_unique($names) as $name) {
            $field = 'id' === $name ? null : $this->table->getValueField($name);
            if ($field && $field->isHiddenInList() && !$field->isHiddenInListDisabled()) {
                $field['list_hidden'] = 0;
                $revealed[] = $name;
            }
        }
        return $revealed;
    }

    /** Übersetztes, getrimmtes Feldlabel (unescaped) */
    public static function label(rex_yform_manager_field $field): string
    {
        return trim(rex_i18n::translate((string) $field->getLabel(), false));
    }

    /**
     * @return array<string, mixed>
     */
    public function linkVars(): array
    {
        return $this->state()->toParams();
    }

    /**
     * @return list<rex_yform_manager_field>
     */
    private function searchableFields(): array
    {
        // nur Felder mit eigener Spalte (nicht: Relationen 1:n ohne Spalte, no_db-Felder)
        $columns = $this->table->getColumns();
        return array_values(array_filter(
            $this->table->getValueFields(),
            static fn ($f) => $f->isSearchable() && array_key_exists($f->getName(), $columns),
        ));
    }

    private static function idField(string $tableName): rex_yform_manager_field
    {
        // wie rex_yform_manager_search::__construct
        return new rex_yform_manager_field([
            'id' => 0,
            'table_name' => $tableName,
            'type_id' => 'value',
            'type_name' => 'integer',
            'search' => 1,
            'label' => 'ID',
            'name' => 'id',
        ]);
    }

    /**
     * @return list<string>
     */
    private static function pipeList(mixed $value): array
    {
        return array_values(array_filter(explode('|', (string) $value), 'strlen'));
    }
}
