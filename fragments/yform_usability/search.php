<?php

/**
 * This file is part of the yform/usability package.
 *
 * Suchleiste der YForm-Tabellenliste: Suchfeld, Zeitraum, „+ Filter“, Chips, Trefferzahl.
 *
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */

use yform\usability\Search\DateRange;
use yform\usability\Search\FieldFilter;
use yform\usability\Search\Operators;
use yform\usability\Search\Search;
use yform\usability\Search\State;

/** @var Search $search */
$search = $this->getVar('search');
$count = $this->getVar('count');
$errors = (array) $this->getVar('errors');

$state = $search->state();
$carry = State::carryOver(Search::request());
$dateFields = $search->dateFields();
$filterFields = $search->filterFields();
$label = Search::label(...);
$quickLabels = array_map($label, $search->quickFields());

// $msg liefert rohen Text, $e den HTML-escapten; escaped wird genau einmal bei der Ausgabe
$msg = static fn (string $key, ...$args): string => rex_i18n::rawMsg('yform_usability.search_' . $key, ...$args);
$e = static fn (string $key, ...$args): string => rex_escape($msg($key, ...$args));
$url = static fn (array $params) => rex_url::backendController(array_merge($carry, $params));

$placeholder = $msg('placeholder', implode(', ', array_slice($quickLabels, 0, 3)) . (count($quickLabels) > 3 ? ', …' : ''));

$formatDate = static function (string $value): string {
    $date = DateRange::parseDate($value);
    return $date ? $date->format('d.m.Y') : $value;
};

/** Anzeigetext eines Filterwerts */
$valueText = static function (rex_yform_manager_field $field, string $op, string|array $value) use ($formatDate): string {
    $values = (array) $value;
    if (in_array($op, ['in', 'not_in'], true)) {
        if ('be_manager_relation' === $field->getTypeName()) {
            $options = FieldFilter::relationOptions($field) ?? [];
            $values = array_map(static fn ($v) => $options[(int) $v] ?? $v, $values);
        } else {
            $choices = FieldFilter::choices($field);
            $values = array_map(static fn ($v) => $choices[$v] ?? $v, $values);
        }
        return implode(', ', $values);
    }
    if (in_array($field->getTypeName(), FieldFilter::DATE_TYPES, true)) {
        $values = array_map($formatDate, $values);
    }
    return implode(' – ', array_filter($values, 'strlen'));
};

/**
 * Bedingung und Wert-Eingaben einer Filterzeile. Nur die Eingabe zur gewählten Bedingung ist aktiv,
 * die übrigen schaltet das JS beim Wechsel der Bedingung frei.
 */
$renderBody = static function (string $name, array $def, string $index, string $op = '', string|array $value = '') use ($msg, $e): string {
    /** @var rex_yform_manager_field $field */
    $field = $def['field'];
    $type = $field->getTypeName();
    $ops = $def['ops'];
    $op = in_array($op, $ops, true) ? $op : $ops[0];
    $id = 'yfu-f-' . $index;
    $base = 'yfu-f[' . $index . '][value]';
    $isDate = in_array($type, FieldFilter::DATE_TYPES, true);
    $isNumber = in_array($type, ['integer', 'number'], true) || 'id' === $name;
    $inputType = $isDate ? 'date' : ($isNumber ? 'number' : 'text');
    $toInput = static fn (string $v) => $isDate && ($d = DateRange::parseDate($v)) ? $d->format('Y-m-d') : $v;

    $html = '<label class="sr-only" for="' . $id . '-op">' . $e('filter_op') . '</label>';
    $html .= '<select class="form-control" id="' . $id . '-op" name="yfu-f[' . $index . '][op]" data-yfu-op>';
    foreach ($ops as $o) {
        $html .= '<option value="' . rex_escape($o) . '"' . ($o === $op ? ' selected' : '') . '>' . rex_escape($msg('op_' . $o)) . '</option>';
    }
    $html .= '</select>';

    // Eingaben je Werttyp: list (in/not_in), range (between), single, none
    $widgets = [];
    if (array_intersect($ops, ['in', 'not_in'])) {
        $selected = Operators::takesList($op) && !in_array($op, ['between'], true) ? (array) $value : [];
        $options = 'be_manager_relation' === $type ? FieldFilter::relationOptions($field) : FieldFilter::choices($field);
        if (null === $options) {
            // große Zieltabelle: Suchtext statt Auswahl
            $w = '<input type="text" class="form-control" id="' . $id . '-list" name="' . $base . '[]" value="' . rex_escape(implode(' ', $selected)) . '" placeholder="' . rex_escape($msg('op_name_contains')) . '">';
        } else {
            $w = '<select multiple class="form-control selectpicker" data-live-search="' . (count($options) > 10 ? 'true' : 'false') . '" data-width="auto" data-container="body" id="' . $id . '-list" name="' . $base . '[]">';
            foreach ($options as $k => $label) {
                $w .= '<option value="' . rex_escape((string) $k) . '"' . (in_array((string) $k, $selected, true) ? ' selected' : '') . '>' . rex_escape($label) . '</option>';
            }
            $w .= '</select>';
        }
        $widgets['list'] = '<label class="sr-only" for="' . $id . '-list">' . $e('filter_value') . '</label>' . $w;
    }
    if (in_array('between', $ops, true)) {
        $range = 'between' === $op ? array_pad((array) $value, 2, '') : ['', ''];
        $widgets['range'] = '<label class="sr-only" for="' . $id . '-from">' . $e('date_from') . '</label>'
            . '<input type="' . $inputType . '" class="form-control" id="' . $id . '-from" name="' . $base . '[]" value="' . rex_escape($toInput($range[0])) . '">'
            . '<span aria-hidden="true">–</span>'
            . '<label class="sr-only" for="' . $id . '-to">' . $e('date_to') . '</label>'
            . '<input type="' . $inputType . '" class="form-control" id="' . $id . '-to" name="' . $base . '[]" value="' . rex_escape($toInput($range[1])) . '">';
    }
    if (array_filter($ops, static fn ($o) => !Operators::takesList($o) && !Operators::takesNoValue($o))) {
        $single = !Operators::takesList($op) && !Operators::takesNoValue($op) ? (string) $value : '';
        $singleType = in_array('name_contains', $ops, true) || in_array('contains', $ops, true) ? 'text' : $inputType;
        $widgets['single'] = '<label class="sr-only" for="' . $id . '-value">' . $e('filter_value') . '</label>'
            . '<input type="' . $singleType . '" class="form-control" id="' . $id . '-value" name="' . $base . '" value="' . rex_escape($toInput($single)) . '">';
    }

    $active = Operators::takesNoValue($op) ? 'none' : ('between' === $op ? 'range' : (Operators::takesList($op) ? 'list' : 'single'));
    foreach ($widgets as $kind => $w) {
        $on = $kind === $active;
        $html .= '<span class="yfu-filter-value" data-yfu-widget="' . $kind . '"' . ($on ? '' : ' hidden') . '>'
            . ($on ? $w : str_replace(['<input ', '<select '], ['<input disabled ', '<select disabled '], $w))
            . '</span>';
    }

    return $html;
};

$renderRow = static function (string $index, string $name = '', string $op = '', string|array $value = '') use ($filterFields, $renderBody, $msg, $e, $label): string {
    $html = '<fieldset class="yfu-filter" data-yfu-filter>';
    $html .= '<legend class="sr-only">' . $e('filter_n', is_numeric($index) ? (int) $index + 1 : '') . '</legend>';
    $html .= '<label class="sr-only" for="yfu-f-' . $index . '-field">' . $e('filter_field') . '</label>';
    $html .= '<select class="form-control" id="yfu-f-' . $index . '-field" name="yfu-f[' . $index . '][field]" data-yfu-field>';
    foreach ($filterFields as $fieldName => $def) {
        $html .= '<option value="' . rex_escape($fieldName) . '"' . ($fieldName === $name ? ' selected' : '') . '>' . rex_escape($label($def['field'])) . '</option>';
    }
    $html .= '</select>';
    $first = '' !== $name ? $name : (string) array_key_first($filterFields);
    $html .= '<span class="yfu-filter-body" data-yfu-body>' . $renderBody($first, $filterFields[$first], $index, $op, $value) . '</span>';
    $html .= '<button type="button" class="btn btn-link yfu-filter-remove" data-yfu-remove title="' . rex_escape($msg('remove_filter')) . '">'
        . '<i class="rex-icon fa-times" aria-hidden="true"></i><span class="sr-only">' . $e('remove_filter') . '</span></button>';
    $html .= '</fieldset>';
    return $html;
};

// Chips
$chips = [];
if ('' !== $state->q) {
    $chips[] = [$msg('chip_q', $state->q), $state->without('q')];
}
if (null !== $state->date) {
    $dateField = $search->table->getValueField($state->date['field']);
    $preset = $state->date['preset'];
    if ('' !== $preset && 'custom' !== $preset) {
        $text = $msg('preset_' . $preset);
    } elseif ('' !== $state->date['from'] && '' !== $state->date['to']) {
        $text = $formatDate($state->date['from']) . ' – ' . $formatDate($state->date['to']);
    } elseif ('' !== $state->date['from']) {
        $text = $msg('date_since', $formatDate($state->date['from']));
    } else {
        $text = $msg('date_until', $formatDate($state->date['to']));
    }
    $chips[] = [$label($dateField) . ': ' . $text, $state->without('date')];
}
foreach ($state->filters as $i => $filter) {
    $field = $filterFields[$filter['field']]['field'];
    $text = $label($field) . ('in' === $filter['op'] ? ':' : ' ' . $msg('op_' . $filter['op']));
    $value = $valueText($field, $filter['op'], $filter['value']);
    $chips[] = [trim($text . ' ' . $value), $state->without('filter', $i)];
}

$datePreset = $state->date['preset'] ?? '';
$dateFrom = $state->date['from'] ?? '';
$dateTo = $state->date['to'] ?? '';
$toIso = static fn (string $v) => ($d = DateRange::parseDate($v)) ? $d->format('Y-m-d') : $v;

?>
<search class="yfu-search" role="search" data-yfu-mark="<?= rex_escape(json_encode($state->highlightTerms(), JSON_UNESCAPED_UNICODE)) ?>">
    <form method="get" action="index.php" class="yfu-search-form" data-yfu-search>
        <?php foreach ($carry as $name => $value): ?>
            <input type="hidden" name="<?= rex_escape($name) ?>" value="<?= rex_escape($value) ?>">
        <?php endforeach ?>

        <div class="yfu-search-bar">
            <div class="yfu-search-q">
                <label class="sr-only" for="yfu-q"><?= $e('label') ?></label>
                <input type="search" class="form-control" id="yfu-q" name="yfu-q" value="<?= rex_escape($state->q) ?>" placeholder="<?= rex_escape($placeholder) ?>">
            </div>

            <?php if ($dateFields): ?>
                <div class="yfu-search-date" role="group" aria-labelledby="yfu-date-label">
                    <?php if (count($dateFields) > 1): ?>
                        <span id="yfu-date-label" class="sr-only"><?= $e('date') ?></span>
                        <label class="sr-only" for="yfu-date-field"><?= $e('date_field') ?></label>
                        <select class="form-control" id="yfu-date-field" name="yfu-date[field]">
                            <?php foreach ($dateFields as $field): ?>
                                <option value="<?= rex_escape($field->getName()) ?>"<?= ($state->date['field'] ?? '') === $field->getName() ? ' selected' : '' ?>><?= rex_escape($label($field)) ?></option>
                            <?php endforeach ?>
                        </select>
                    <?php else: ?>
                        <span id="yfu-date-label" class="yfu-search-date-label"><?= rex_escape($label($dateFields[0])) ?></span>
                        <input type="hidden" name="yfu-date[field]" value="<?= rex_escape($dateFields[0]->getName()) ?>">
                    <?php endif ?>
                    <label class="sr-only" for="yfu-date-preset"><?= $e('date') ?></label>
                    <select class="form-control" id="yfu-date-preset" name="yfu-date[preset]" data-yfu-preset>
                        <option value=""><?= $e('date_any') ?></option>
                        <?php foreach (DateRange::PRESETS as $preset): ?>
                            <option value="<?= $preset ?>"<?= $preset === $datePreset ? ' selected' : '' ?>><?= $e('preset_' . $preset) ?></option>
                        <?php endforeach ?>
                    </select>
                    <span class="yfu-search-range" data-yfu-range>
                        <label for="yfu-date-from"><?= $e('date_from') ?></label>
                        <input type="date" class="form-control" id="yfu-date-from" name="yfu-date[from]" value="<?= rex_escape($toIso($dateFrom)) ?>">
                        <label for="yfu-date-to"><?= $e('date_to') ?></label>
                        <input type="date" class="form-control" id="yfu-date-to" name="yfu-date[to]" value="<?= rex_escape($toIso($dateTo)) ?>">
                    </span>
                </div>
            <?php endif ?>

            <div class="yfu-search-actions">
                <?php if ($filterFields): ?>
                    <button type="button" class="btn btn-default" data-yfu-add aria-controls="yfu-filters"><?= $e('add_filter') ?></button>
                <?php endif ?>
                <?php /* name nötig: REDAXOs PJAX hängt den Namen des geklickten Buttons an, sonst "undefined=" */ ?>
                <button type="submit" class="btn btn-primary" name="yfu-submit" value="1"><?= $e('submit') ?></button>
            </div>
        </div>

        <div class="yfu-search-filters" id="yfu-filters" data-yfu-filters data-yfu-legend="<?= rex_escape($msg('filter_n', '{0}')) ?>">
            <?php foreach ($state->filters as $i => $filter): ?>
                <?= $renderRow((string) $i, $filter['field'], $filter['op'], $filter['value']) ?>
            <?php endforeach ?>
        </div>

        <template data-yfu-template="row"><?= $renderRow('__i__') ?></template>
        <?php foreach ($filterFields as $fieldName => $def): ?>
            <template data-yfu-template="field" data-yfu-name="<?= rex_escape($fieldName) ?>"><?= $renderBody($fieldName, $def, '__i__') ?></template>
        <?php endforeach ?>
    </form>

    <div role="alert">
        <?php foreach ($errors as $error): ?>
            <p class="alert alert-warning"><?= rex_escape($error) ?></p>
        <?php endforeach ?>
    </div>

    <?php if ($chips): ?>
        <div class="yfu-search-active">
            <ul class="yfu-search-chips">
                <?php foreach ($chips as [$text, $without]): ?>
                    <li>
                        <a class="yfu-chip" href="<?= $url($without->toParams()) ?>">
                            <span aria-hidden="true"><?= rex_escape($text) ?></span>
                            <span class="sr-only"><?= rex_escape($msg('remove_chip', $text)) ?></span>
                            <i class="rex-icon fa-times" aria-hidden="true"></i>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
            <a class="yfu-search-reset" href="<?= $url([]) ?>"><?= $e('reset') ?></a>
            <?php if (null !== $count): ?>
                <p class="yfu-search-count" role="status" data-yfu-count="<?= rex_escape($msg('count', (int) $count)) ?>"></p>
            <?php endif ?>
        </div>
    <?php endif ?>
</search>
