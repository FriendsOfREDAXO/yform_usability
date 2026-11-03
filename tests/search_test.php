<?php

/**
 * Tests für die reinen Hilfsklassen der Suche (ohne REDAXO lauffähig).
 *
 * Aufruf: php tests/search_test.php
 */

declare(strict_types=1);

use yform\usability\Search\DateRange;
use yform\usability\Search\Operators;
use yform\usability\Search\State;
use yform\usability\Search\Terms;

foreach (['Terms', 'DateRange', 'Operators', 'State'] as $class) {
    $file = __DIR__ . '/../lib/Search/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
}

$failures = 0;
$count = 0;

function check(string $name, mixed $actual, mixed $expected): void
{
    global $failures, $count;
    ++$count;
    $ok = is_object($expected) ? $actual == $expected : $actual === $expected;
    if (!$ok) {
        ++$failures;
        echo "FAIL $name\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
    }
}

// --- Terms -------------------------------------------------------------------

check('split simple', Terms::split('ibu  15687 '), ['ibu', '15687']);
check('split strips star', Terms::split('*profen* 50-*'), ['profen', '50-']);
check('split phrase', Terms::split('"acetyl salicyl" säure'), ['acetyl salicyl', 'säure']);
check('split shy', Terms::split("Ibu\u{00AD}profen"), ['Ibuprofen']);
check('split shy entity', Terms::split('Ibu&shy;profen'), ['Ibuprofen']);
check('split only stars', Terms::split(' * ** '), []);
check('split max 10', count(Terms::split(str_repeat('a ', 15))), 10);
check('escapeLike', Terms::escapeLike('50%_a\\b'), '50\\%\\_a\\\\b');
check('likeContains', Terms::likeContains('a_b'), '%a\\_b%');
check('normalizedColumn', Terms::normalizedColumn('`t0`.`name`'), "REPLACE(REPLACE(`t0`.`name`, UNHEX('C2AD'), ''), '&shy;', '')");

// --- DateRange ---------------------------------------------------------------

$today = new DateTimeImmutable('2026-03-15');
$r = DateRange::resolve('last7', '', '', $today);
check('last7', [$r->sqlFrom(), $r->sqlToExclusive()], ['2026-03-09 00:00:00', '2026-03-16 00:00:00']);
$r = DateRange::resolve('last30', '', '', $today);
check('last30 from', $r->sqlFrom(), '2026-02-14 00:00:00');
$r = DateRange::resolve('this_month', '', '', new DateTimeImmutable('2026-02-10'));
check('this_month', [$r->sqlFrom(), $r->sqlToExclusive()], ['2026-02-01 00:00:00', '2026-03-01 00:00:00']);
$r = DateRange::resolve('this_year', '', '', $today);
check('this_year', [$r->sqlFrom(), $r->sqlToExclusive()], ['2026-01-01 00:00:00', '2027-01-01 00:00:00']);
$r = DateRange::resolve('last_year', '', '', $today);
check('last_year', [$r->sqlFrom(), $r->sqlToExclusive()], ['2025-01-01 00:00:00', '2026-01-01 00:00:00']);
$r = DateRange::resolve('today', '', '', $today);
check('today', [$r->sqlFrom(), $r->sqlToExclusive()], ['2026-03-15 00:00:00', '2026-03-16 00:00:00']);
$r = DateRange::resolve('custom', '01.08.2026', '', $today);
check('open end', [$r->sqlFrom(), $r->sqlToExclusive(), $r->error], ['2026-08-01 00:00:00', null, null]);
$r = DateRange::resolve('custom', '', '2026-12-31', $today);
check('open start', [$r->sqlFrom(), $r->sqlToExclusive()], [null, '2027-01-01 00:00:00']);
check('preset wins over dates', DateRange::resolve('today', '2020-01-01', '', $today)->sqlFrom(), '2026-03-15 00:00:00');
check('dates without preset = custom', DateRange::resolve('', '2026-05-01', '', $today)->sqlFrom(), '2026-05-01 00:00:00');
check('from after to', DateRange::resolve('custom', '2026-05-02', '2026-05-01', $today)->error, 'from_after_to');
check('same day ok', DateRange::resolve('custom', '2026-05-01', '2026-05-01', $today)->error, null);
check('invalid date', DateRange::resolve('custom', '31.02.2026', '', $today)->error, 'invalid_date');
check('garbage date', DateRange::resolve('custom', 'gestern', '', $today)->error, 'invalid_date');
check('nothing', DateRange::resolve('', '', '', $today), null);
check('custom without dates', DateRange::resolve('custom', '', '', $today), null);
check('unknown preset ignored', DateRange::resolve('yesterday', '', '', $today), null);
check('parseDate de', DateRange::parseDate('1.8.2026')?->format('Y-m-d'), '2026-08-01');

// --- Operators ---------------------------------------------------------------

$textOps = ['contains', 'equals', 'not_contains', 'empty', 'not_empty'];
check('ops text', Operators::forType('text', 'varchar(191)'), $textOps);
check('ops textarea', Operators::forType('textarea', 'text'), $textOps);
check('ops integer', Operators::forType('integer', 'int'), ['eq', 'neq', 'gt', 'lt', 'between', 'empty']);
check('ops datetime', Operators::forType('datetime', 'datetime'), ['between', 'before', 'after', 'empty']);
check('ops choice', Operators::forType('choice', 'text'), ['in', 'not_in', 'empty']);
check('ops checkbox', Operators::forType('checkbox', 'tinyint(1)'), ['checked', 'unchecked']);
check('ops relation', Operators::forType('be_manager_relation', 'text'), ['in', 'empty']);
check('ops media', Operators::forType('be_media', 'text'), ['set', 'not_set', 'name_contains']);
check('ops unknown text col', Operators::forType('google_geocode', 'text'), $textOps);
check('ops unknown varchar col', Operators::forType('foo', 'VARCHAR(191)'), $textOps);
check('ops unknown int col', Operators::forType('foo', 'int'), []);
check('ops prio', Operators::forType('prio', 'int'), []);
check('ops be_table', Operators::forType('be_table', 'text'), []);
check('ops html', Operators::forType('html', ''), []);
check('ops isMulti', [Operators::takesList('in'), Operators::takesList('between'), Operators::takesList('contains')], [true, true, false]);
check('ops noValue', [Operators::takesNoValue('empty'), Operators::takesNoValue('checked'), Operators::takesNoValue('eq')], [true, true, false]);

// --- State -------------------------------------------------------------------

$allowed = ['date' => ['modified'], 'filter' => ['status' => ['in', 'not_in', 'empty'], 'name' => $textOps]];
$get = [
    'yfu-q' => ' ibu ',
    'yfu-date' => ['field' => 'modified', 'preset' => 'last30', 'from' => '', 'to' => ''],
    'yfu-f' => [
        ['field' => 'status', 'op' => 'in', 'value' => ['1']],
        ['field' => 'secret', 'op' => 'contains', 'value' => 'x'],
        ['field' => 'name', 'op' => 'drop table', 'value' => 'x'],
        ['field' => ['x'], 'op' => 'in', 'value' => '1'],
        ['field' => 'status', 'op' => 'in', 'value' => '1'],
        ['field' => 'name', 'op' => 'contains', 'value' => ['a', 'b']],
        ['field' => 'name', 'op' => 'contains', 'value' => ''],
        ['field' => 'name', 'op' => 'empty'],
        'kaputt',
    ],
];
$s = State::fromRequest($get, $allowed);
check('state q', $s->q, 'ibu');
check('state date', $s->date, ['field' => 'modified', 'preset' => 'last30', 'from' => '', 'to' => '']);
check('state filters', $s->filters, [
    ['field' => 'status', 'op' => 'in', 'value' => ['1']],
    ['field' => 'status', 'op' => 'in', 'value' => ['1']],
    ['field' => 'name', 'op' => 'empty', 'value' => ''],
]);
check('state q not string', State::fromRequest(['yfu-q' => ['x']], $allowed)->q, '');
check('state date field not allowed', State::fromRequest(['yfu-date' => ['field' => 'createdate', 'preset' => 'today']], $allowed)->date, null);
check('state date bad preset', State::fromRequest(['yfu-date' => ['field' => 'modified', 'preset' => 'nope']], $allowed)->date, null);
check('state date empty', State::fromRequest(['yfu-date' => ['field' => 'modified', 'preset' => '', 'from' => '', 'to' => '']], $allowed)->date, null);
check('state date not array', State::fromRequest(['yfu-date' => 'x'], $allowed)->date, null);
check('state filters not array', State::fromRequest(['yfu-f' => 'x'], $allowed)->filters, []);
check('state without filter 0', count($s->without('filter', 0)->filters), 2);
check('state without q', $s->without('q')->q, '');
check('state without date', $s->without('date')->date, null);
check('state toParams', $s->toParams(), [
    'yfu-q' => 'ibu',
    'yfu-date' => ['field' => 'modified', 'preset' => 'last30', 'from' => '', 'to' => ''],
    'yfu-f' => [
        ['field' => 'status', 'op' => 'in', 'value' => ['1']],
        ['field' => 'status', 'op' => 'in', 'value' => ['1']],
        ['field' => 'name', 'op' => 'empty', 'value' => ''],
    ],
]);
check('state toParams roundtrip', State::fromRequest($s->toParams(), $allowed), $s);
check('state toParams empty', State::fromRequest([], $allowed)->toParams(), []);
check('state inactive', State::fromRequest([], $allowed)->isActive(), false);
check('state active', $s->isActive(), true);
check('carryOver', State::carryOver([
    'page' => 'yform/manager/data_edit',
    'table_name' => 't',
    'rex_yform_filter' => ['status' => '1'],
    'rex_yform_manager_opener' => ['id' => '3', 'field' => 'x'],
    'yfu-q' => 'x',
    'yfu-f' => [['field' => 'a']],
    'start' => '30',
    'func' => 'edit',
    'data_id' => '5',
    'list' => 'abc',
    'sort' => 'name',
    'd16e0e0e_start' => '200',
    '_csrf_token' => 'x',
]), [
    'page' => 'yform/manager/data_edit',
    'table_name' => 't',
    'rex_yform_filter[status]' => '1',
    'rex_yform_manager_opener[id]' => '3',
    'rex_yform_manager_opener[field]' => 'x',
    'sort' => 'name',
]);

// Massenbearbeitung/Speichern schicken per POST ohne Query-String: Zustand und Manager-Parameter aus POST übernehmen,
// Formularfelder des Datensatzes nicht
check('requestParams POST', State::requestParams(
    ['page' => 'yform/manager/data_edit'],
    [
        'yfu-q' => 'ibu',
        'yfu-f' => [['field' => 'status', 'op' => 'in', 'value' => ['1']]],
        'table_name' => 't',
        'rex_yform_filter' => ['status' => '1'],
        'rex_yform_manager_popup' => '0',
        'sort' => 'name',
        'sorttype' => 'asc',
        'FORM' => ['x' => 'geheim'],
        'name_1' => 'Datensatzwert',
        'func' => 'edit',
    ],
), [
    'page' => 'yform/manager/data_edit',
    'yfu-q' => 'ibu',
    'yfu-f' => [['field' => 'status', 'op' => 'in', 'value' => ['1']]],
    'table_name' => 't',
    'rex_yform_filter' => ['status' => '1'],
    'rex_yform_manager_popup' => '0',
    'sort' => 'name',
    'sorttype' => 'asc',
]);
check('requestParams GET wins', State::requestParams(['yfu-q' => 'a'], ['yfu-q' => 'b']), ['yfu-q' => 'a']);

// Fundstellen markieren: Suchwörter überall, „enthält“-Werte nur in ihrer Spalte, erst ab zwei Zeichen
$hl = new State('"acetyl salicyl" ibu 1 *', null, [
    ['field' => 'cas_number', 'op' => 'contains', 'value' => "50-0\u{00AD}0"],
    ['field' => 'name', 'op' => 'equals', 'value' => 'x'],
    ['field' => 'img', 'op' => 'name_contains', 'value' => 'png'],
    ['field' => 'status', 'op' => 'in', 'value' => ['1']],
]);
check('highlightTerms', $hl->highlightTerms(), [
    'all' => ['acetyl salicyl', 'ibu'],
    'fields' => ['cas_number' => ['50-00'], 'img' => ['png']],
]);
check('highlightTerms leer', (new State())->highlightTerms(), ['all' => [], 'fields' => []]);

// -----------------------------------------------------------------------------

echo $count - $failures . "/$count ok\n";
exit($failures > 0 ? 1 : 0);
