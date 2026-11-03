<?php

/**
 * This file is part of the yform/usability package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability\Search;

/**
 * Suchzustand aus der URL: Suchwort, Zeitraum, Filter. Alles Unerlaubte wird verworfen.
 */
final class State
{
    private const OWN_PREFIX = 'yfu-';

    /** Parameter, die beim Absenden einer neuen Suche nicht mitgenommen werden (dazu <liste>_start von rex_list) */
    private const DROP_PARAMS = ['start', 'func', 'data_id', 'list', '_csrf_token'];

    /** Parameter des YForm-Managers, die auch aus einem POST übernommen werden */
    private const MANAGER_PARAMS = ['page', 'table_name', 'rex_yform_filter', 'rex_yform_set', 'rex_yform_manager_opener', 'rex_yform_manager_popup', 'sort', 'sorttype'];

    /**
     * @param array{field: string, preset: string, from: string, to: string}|null $date
     * @param list<array{field: string, op: string, value: string|list<string>}> $filters
     */
    public function __construct(
        public readonly string $q = '',
        public readonly ?array $date = null,
        public readonly array $filters = [],
    ) {
    }

    /**
     * @param array<mixed> $get
     * @param array{date: list<string>, filter: array<string, list<string>>} $allowed
     */
    public static function fromRequest(array $get, array $allowed): self
    {
        $q = is_string($get['yfu-q'] ?? null) ? trim($get['yfu-q']) : '';

        return new self($q, self::readDate($get['yfu-date'] ?? null, $allowed['date']), self::readFilters($get['yfu-f'] ?? null, $allowed['filter']));
    }

    public function isActive(): bool
    {
        return '' !== $this->q || null !== $this->date || [] !== $this->filters;
    }

    /**
     * @return array<string, mixed>
     */
    public function toParams(): array
    {
        $params = [];
        if ('' !== $this->q) {
            $params['yfu-q'] = $this->q;
        }
        if (null !== $this->date) {
            $params['yfu-date'] = $this->date;
        }
        if ([] !== $this->filters) {
            $params['yfu-f'] = array_values($this->filters);
        }
        return $params;
    }

    /**
     * Was in der Liste markiert wird: Suchwörter in allen Spalten, Textfilter-Werte nur in ihrer Spalte.
     * Unter zwei Zeichen wird nichts markiert.
     *
     * @return array{all: list<string>, fields: array<string, list<string>>}
     */
    public function highlightTerms(): array
    {
        $long = static fn (string $term): bool => mb_strlen($term) >= 2;

        $fields = [];
        foreach ($this->filters as $filter) {
            if (in_array($filter['op'], ['contains', 'equals', 'name_contains'], true) && is_string($filter['value'])) {
                $value = trim(str_replace(["\u{00AD}", '&shy;'], '', $filter['value']));
                if ($long($value)) {
                    $fields[$filter['field']][] = $value;
                }
            }
        }

        return ['all' => array_values(array_filter(Terms::split($this->q), $long)), 'fields' => $fields];
    }

    /**
     * @param 'q'|'date'|'filter' $part
     */
    public function without(string $part, ?int $index = null): self
    {
        $filters = $this->filters;
        if ('filter' === $part) {
            if (null === $index) {
                $filters = [];
            } else {
                unset($filters[$index]);
                $filters = array_values($filters);
            }
        }

        return new self(
            'q' === $part ? '' : $this->q,
            'date' === $part ? null : $this->date,
            $filters,
        );
    }

    /**
     * Anfrageparameter für Suche und Hidden-Felder. Massenbearbeitung und Speichern schicken per POST
     * ohne Query-String; aus POST kommen nur die Suche und die Manager-Parameter, nie Formularwerte.
     *
     * @param array<mixed> $get
     * @param array<mixed> $post
     * @return array<mixed>
     */
    public static function requestParams(array $get, array $post): array
    {
        $fromPost = array_filter(
            $post,
            static fn ($key) => str_starts_with((string) $key, self::OWN_PREFIX) || in_array($key, self::MANAGER_PARAMS, true),
            ARRAY_FILTER_USE_KEY,
        );
        return $get + $fromPost;
    }

    /**
     * Alle übrigen GET-Parameter als flache Name/Wert-Paare für Hidden-Felder.
     *
     * @param array<mixed> $get
     * @return array<string, string>
     */
    public static function carryOver(array $get): array
    {
        $result = [];
        foreach ($get as $key => $value) {
            $key = (string) $key;
            if (str_starts_with($key, self::OWN_PREFIX) || in_array($key, self::DROP_PARAMS, true) || str_ends_with($key, '_start')) {
                continue;
            }
            self::flatten($key, $value, $result);
        }
        return $result;
    }

    /**
     * @param array<string, string> $result
     */
    private static function flatten(string $name, mixed $value, array &$result): void
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                self::flatten($name . '[' . $k . ']', $v, $result);
            }
        } elseif (is_scalar($value)) {
            $result[$name] = (string) $value;
        }
    }

    /**
     * @param list<string> $allowedFields
     * @return array{field: string, preset: string, from: string, to: string}|null
     */
    private static function readDate(mixed $raw, array $allowedFields): ?array
    {
        if (!is_array($raw) || !is_string($raw['field'] ?? null) || !in_array($raw['field'], $allowedFields, true)) {
            return null;
        }

        $preset = is_string($raw['preset'] ?? null) ? $raw['preset'] : '';
        if ('' !== $preset && !in_array($preset, DateRange::PRESETS, true)) {
            return null;
        }
        $from = is_string($raw['from'] ?? null) ? trim($raw['from']) : '';
        $to = is_string($raw['to'] ?? null) ? trim($raw['to']) : '';

        if (('' === $preset || 'custom' === $preset) && '' === $from && '' === $to) {
            return null;
        }

        return ['field' => $raw['field'], 'preset' => $preset, 'from' => $from, 'to' => $to];
    }

    /**
     * @param array<string, list<string>> $allowed
     * @return list<array{field: string, op: string, value: string|list<string>}>
     */
    private static function readFilters(mixed $raw, array $allowed): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $filters = [];
        foreach ($raw as $item) {
            if (!is_array($item) || !is_string($item['field'] ?? null) || !is_string($item['op'] ?? null)) {
                continue;
            }
            $field = $item['field'];
            $op = $item['op'];
            if (!in_array($op, $allowed[$field] ?? [], true)) {
                continue;
            }

            $value = $item['value'] ?? '';
            if (Operators::takesNoValue($op)) {
                $value = '';
            } elseif (Operators::takesList($op)) {
                $value = is_array($value) ? $value : [$value];
                $value = array_values(array_map(static fn ($v) => trim((string) $v), array_filter($value, 'is_scalar')));
                if ('' === implode('', $value)) {
                    continue;
                }
            } else {
                if (!is_scalar($value) || '' === trim((string) $value)) {
                    continue;
                }
                $value = trim((string) $value);
            }

            $filters[] = ['field' => $field, 'op' => $op, 'value' => $value];
        }
        return $filters;
    }
}
