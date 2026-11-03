<?php

/**
 * This file is part of the yform/usability package.
 *
 * @author Friends Of REDAXO
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability;

use rex;
use rex_addon;
use rex_api_function;
use rex_api_yform_usability_api;
use rex_csrf_token;
use rex_extension;
use rex_extension_point;
use rex_fragment;
use rex_i18n;
use rex_response;
use rex_sql;
use rex_url;
use rex_version;
use rex_yform_list;
use rex_yform_manager_field;
use rex_yform_manager_table;
use Sprog\Wildcard;
use yform\usability\Search\Search;
use function rex_request;

class Extensions
{
    public static function init(): void
    {
        rex_extension::register('YFORM_DATA_UPDATED', self::ext__dataUpdated(...));

        rex_extension::register('PACKAGES_INCLUDED', function () {
            if (rex_request('rex-api-call', 'string') === 'yform_usability_api') {
                // api endpoint
                $api_result = rex_api_yform_usability_api::factory();

                rex_api_function::handleCall();

                if ($api_result && $api_result->getResult()) {
                    rex_response::cleanOutputBuffers();
                    rex_response::setStatus(rex_response::HTTP_OK);
                    rex_response::sendContent($api_result->getResult()->toJSON(), 'application/json');
                    exit;
                }
            }
        });

        if (rex::isBackend()) {
            rex_extension::register(
                'yform/usability.getStatusColumnParams.options',
                self::ext__getStatusColumnOptions(...)
            );
        }
    }

    protected static function addStatusToggle($list, $table): rex_yform_list
    {
        $config = Usability::getConfig(); // load settings
        $status_position = count($list->getColumnNames()); // default: column position at the end
        
        // get all yform table manager tablenames
        $tables = [];
        foreach (rex_yform_manager_table::getAll() as $yform_table) {
            $tables[] = $yform_table->getTableName();
        }
        
        // move columns of all/selected tables if conditions are met
        if ((isset($config['status_first_column_all']) && $config['status_first_column_all'] === '|1|') || in_array($table->getTableName(), explode('|', trim($config['status_first_column_tables'] ?? '', '|')))) {
            $status_position = 2; // 0 = icon column, 1 = id column
        }
        
        // transform existing status column (only if it's already visible in the list)
        // und nur, wenn das Feld tatsaechlich von diesem AddOn dargestellt werden kann -
        // eigene Feldtypen (z.B. ein individueller Inline-Switch) bleiben sonst unveraendert,
        // statt mit einem leeren/falschen Status-Icon ueberschrieben zu werden.
        if (in_array('status', $list->getColumnNames()) && Utils::hasKnownStatusOptions($table)) {
            $list->setColumnFormat(
                'status',
                'custom',
                function ($params) {
                    $value = $params['list']->getValue('status');
                    $tparams = Utils::getStatusColumnParams($params['params']['table'], $value, $params['list']);

                    return strtr(
                        $tparams['element'],
                        [
                            '{{ID}}' => $params['list']->getValue('id'),
                            '{{TABLE}}' => $params['params']['table']->getTableName(),
                        ]
                    );
                },
                ['table' => $table]
            );
            $list->setColumnPosition('status', $status_position);
        }
        
        return $list;
    }

    protected static function addDragNDropSort($list, $table)
    {
        $firstColName = current($list->getColumnNames());
        $orderBy      = rex_get('sort', 'string', $table->getSortFieldName());


        if ($firstColName != 'id' && $orderBy === 'prio') {
            $list->addFormAttribute('class', 'sortable-list');
            
            // Add drag handle column before first column (but keep edit icon)
            $list->addColumn('yform_drag_handle', '', 0);
            $list->setColumnLabel('yform_drag_handle', '');
            $list->setColumnLayout('yform_drag_handle', ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
            
            $list->setColumnFormat(
                'yform_drag_handle',
                'custom',
                function ($params) use ($table) {
                    $filters = rex_extension::registerPoint(
                        new rex_extension_point(
                            'yform/usability.addDragNDropSort.filters',
                            [],
                            [
                                'list_params' => $params,
                                'table'       => $table,
                            ]
                        )
                    );
                    $filter = implode(',', $filters);
                    $url = rex_url::currentBackendPage(['method' => 'updateSort', 'filter_sig' => rex_api_yform_usability_api::filterSignature($filter)] + rex_api_yform_usability_api::getUrlParams());
                    return '
                        <i class="rex-icon fa fa-bars sort-icon sort-handle"
                            data-id="###id###"
                            data-url="'.$url . '"
                            data-table-type="orm_model"
                            data-table="' . rex_escape($table->getTableName()) . '"
                            data-filter="' . rex_escape($filter) . '"></i>
                    ';
                },
                ['table' => $table]
            );
        }
        return $list;
    }

    protected static function addThumbnailFormatting($list, $table)
    {
        $tableName = $table->getTableName();
        $thumbnailMappings = ThumbnailManager::getMappingsForTable($tableName);
        
        if (empty($thumbnailMappings)) {
            return $list;
        }
        
        // Get column names from the list
        $columnNames = $list->getColumnNames();
        
        foreach ($thumbnailMappings as $columnName => $mapping) {
            // Check if this column exists in the list
            if (!in_array($columnName, $columnNames)) {
                continue;
            }
            
            $thumbSize = $mapping['thumb_size'] ?? 'rex_thumbnail_default';
            
            // Format the column to show thumbnails
            $list->setColumnFormat(
                $columnName,
                'custom',
                function ($params) use ($thumbSize) {
                    $filename = (string) $params['list']->getValue($params['params']['column_name']);
                    return ThumbnailManager::generateThumbnailHtml($filename, $thumbSize);
                },
                ['column_name' => $columnName]
            );
        }
        
        return $list;
    }

    public static function getArrayFromString($string)
    {
        if (is_array($string)) {
            return $string;
        }
        $delimeter  = ',';
        $rawOptions = preg_split('~(?<!\\\)' . preg_quote($delimeter, '~') . '~', (string) $string) ?: [];
        $options    = [];
        foreach ($rawOptions as $option) {
            $delimeter   = '=';
            $finalOption = preg_split('~(?<!\\\)' . preg_quote($delimeter, '~') . '~', $option) ?: [$option];
            $v           = $finalOption[0];
            if (isset($finalOption[1])) {
                $k = $finalOption[1];
            } else {
                $k = $finalOption[0];
            }
            $s           = ['\=', '\,'];
            $r           = ['=', ','];
            $k           = str_replace($s, $r, $k);
            $v           = str_replace($s, $r, $v);
            $options[$k] = $v;
        }
        return $options;
    }

    public static function ext_rexListGet(rex_extension_point $ep): void
    {
        $list    = $ep->getSubject();
        $lparams = $list->getParams();

        if (($lparams['page'] ?? '') === 'yform/manager/table_field') {
            $list->addFormAttribute('class', 'sortable-list');

            $firstColName = current($list->getColumnNames());
            $tableName    = rex_yform_manager_field::table();


            $list->setColumnLayout($firstColName, ['<th class="rex-table-icon">###VALUE###</th>', '###VALUE###']);
            $list->setColumnFormat(
                $firstColName,
                'custom',
                function ($params) {
                    $filters = rex_extension::registerPoint(
                        new rex_extension_point(
                            'yform/usability.addDragNDropSort.filters',
                            [],
                            ['list_params' => $params]
                        )
                    );

                    switch ($params['list']->getValue('type_id')) {
                        case 'validate':
                            $style = 'color:#aaa;'; // background-color:#cfd9d9;
                            break;
                        case 'action':
                            $style = 'background-color:#cfd9d9;';
                            break;
                        default:
                            // NOTE: because of dark mode (1st version)
                            $style = '';
                            // $style = 'background-color:#eff9f9;';
                            break;
                    }
                    $filter = implode(',', $filters);
                    $url = rex_url::currentBackendPage(['method' => 'updateSort', 'filter_sig' => rex_api_yform_usability_api::filterSignature($filter)] + rex_api_yform_usability_api::getUrlParams());
                    return '
                    <td class="rex-table-icon" style="' . $style . '">
                        <i class="rex-icon fa fa-bars sort-icon"
                        data-url="' . $url . '"
                            data-id="###id###"
                            data-table-type="db_table"
                            data-table-sort-field="prio"
                            data-table-sort-order="asc"
                            data-table="' . rex_escape($params['params']['yform_table']) . '"
                            data-filter="' . rex_escape($filter) . '"></i>
                    </td>
                ';
                },
                ['yform_table' => $lparams['table_name'], 'table' => $tableName]
            );
            
            // Add duplicate button column (nur wenn aktiviert)
            $config = Usability::getConfig();
            $duplicateEnabled = ($config['duplicate_tables_all'] ?? '') === '|1|' || in_array($lparams['table_name'] ?? '', explode('|', trim($config['duplicate_tables'] ?? '', '|')));
            
            if ($duplicateEnabled) {
                $table = rex_yform_manager_table::get($lparams['table_name']);
                $_csrf_key = $table->getCSRFKey();
                $csrf_params = rex_csrf_token::factory($_csrf_key)->getUrlParams();
                $csrf_param = key($csrf_params) . '=' . current($csrf_params);
                $tableName = $lparams['table_name'];
                
                $list->addColumn('duplicate', '');
                $list->setColumnLabel('duplicate', rex_i18n::msg('yform_usability_action.duplicate'));
                $list->setColumnLayout('duplicate', ['', '<td class="rex-table-action">###VALUE###</td>']);
                $list->setColumnFormat('duplicate', 'custom', function($params) use ($csrf_param, $tableName) {
                    $id = $params['list']->getValue('id');
                    $name = $params['list']->getValue('name');
                    
                    // Pass parameters separately to avoid & escaping issues
                    $jsName = str_replace("'", "\\'", $name);
                    $jsTableName = str_replace("'", "\\'", $tableName);
                    
                    return '<a href="#" onclick="if(typeof yformDuplicateField === \'function\') { yformDuplicateField(\'' . $jsTableName . '\', ' . $id . ', \'' . $jsName . '\', \'' . $csrf_param . '\'); } else { alert(\'Funktion nicht gefunden!\'); } return false;" title="Duplizieren">' .
                           '<i class="rex-icon rex-icon-duplicate"></i></a>';
                });
            }
        }
    }

    public static function ext_tableFieldFunc(rex_extension_point $ep): void
    {
        $func = rex_request('func', 'string');
        
        if ($func !== 'duplicate') {
            return;
        }
        
        // Get table_name from request and load table
        $table_name = rex_request('table_name', 'string');
        
        if (empty($table_name)) {
            $_SESSION['yform_usability_message'] = ['type' => 'error', 'text' => 'Kein Tabellenname übergeben'];
            rex_response::sendRedirect('index.php?page=yform/manager/table_field');
            exit;
        }
        
        $table = rex_yform_manager_table::get($table_name);
        
        if (!$table) {
            $_SESSION['yform_usability_message'] = ['type' => 'error', 'text' => 'Tabelle nicht gefunden'];
            rex_response::sendRedirect('index.php?page=yform/manager/table_field');
            exit;
        }
        
        $field_id = rex_request('field_id', 'int');
        $new_name = rex_request('new_name', 'string', '');
        $_csrf_key = $table->getCSRFKey();
        
        // CSRF-Token validieren
        if (!rex_csrf_token::factory($_csrf_key)->isValid()) {
            $_SESSION['yform_usability_message'] = ['type' => 'error', 'text' => rex_i18n::msg('csrf_token_invalid')];
            rex_response::sendRedirect('index.php?page=yform/manager/table_field&table_name=' . urlencode($table->getTableName()));
            exit;
        }
        
        if ($field_id <= 0) {
            $_SESSION['yform_usability_message'] = ['type' => 'error', 'text' => 'Keine gültige Feld-ID übergeben'];
            rex_response::sendRedirect('index.php?page=yform/manager/table_field&table_name=' . urlencode($table->getTableName()));
            exit;
        }
        
        if (empty($new_name)) {
            $_SESSION['yform_usability_message'] = ['type' => 'error', 'text' => 'Kein neuer Feldname angegeben'];
            rex_response::sendRedirect('index.php?page=yform/manager/table_field&table_name=' . urlencode($table->getTableName()));
            exit;
        }
        
        // Validiere Feldname
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $new_name)) {
            $_SESSION['yform_usability_message'] = ['type' => 'error', 'text' => 'Ungültiger Feldname. Nur Buchstaben, Zahlen und Unterstriche erlaubt. Muss mit einem Buchstaben beginnen.'];
            rex_response::sendRedirect('index.php?page=yform/manager/table_field&table_name=' . urlencode($table->getTableName()));
            exit;
        }
        
        // Feld laden
        $sourceSql = rex_sql::factory();
        $sourceSql->setQuery('SELECT * FROM ' . rex::getTable('yform_field') . ' WHERE id = ? AND table_name = ?', [$field_id, $table->getTableName()]);
        
        if ($sourceSql->getRows() == 0) {
            $_SESSION['yform_usability_message'] = ['type' => 'error', 'text' => 'Das zu duplizierende Feld wurde nicht gefunden'];
            rex_response::sendRedirect('index.php?page=yform/manager/table_field&table_name=' . urlencode($table->getTableName()));
            exit;
        }
        
        // Prüfe ob Name bereits existiert
        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('yform_field') . ' WHERE table_name = ? AND name = ?', [$table->getTableName(), $new_name]);
        
        if ($checkSql->getRows() > 0) {
            $_SESSION['yform_usability_message'] = ['type' => 'error', 'text' => 'Ein Feld mit dem Namen "' . $new_name . '" existiert bereits.'];
            rex_response::sendRedirect('index.php?page=yform/manager/table_field&table_name=' . urlencode($table->getTableName()));
            exit;
        }
        
        // Neues Feld erstellen - direkt nach dem Original (prio + 1)
        $newSql = rex_sql::factory();
        $newSql->setTable(rex::getTable('yform_field'));
        
        $originalPrio = (int) $sourceSql->getValue('prio');
        
        // Alle Felder mit prio > originalPrio um 1 erhöhen
        $updateSql = rex_sql::factory();
        $updateSql->setQuery(
            'UPDATE ' . rex::getTable('yform_field') . ' SET prio = prio + 1 WHERE table_name = ? AND prio > ?',
            [$table->getTableName(), $originalPrio]
        );
        
        // Kopiere alle Felder außer id und name
        $columns = $sourceSql->getFieldnames();
        foreach ($columns as $column) {
            if ($column === 'id') {
                continue;
            }
            if ($column === 'name') {
                $newSql->setValue('name', $new_name);
            } elseif ($column === 'prio') {
                $newSql->setValue('prio', $originalPrio + 1);
            } else {
                $newSql->setValue($column, $sourceSql->getValue($column));
            }
        }
        
        try {
            $newSql->insert();
            
            $_SESSION['yform_usability_message'] = ['type' => 'success', 'text' => 'Feld "' . $new_name . '" wurde erfolgreich dupliziert.'];
        } catch (\Exception $e) {
            $_SESSION['yform_usability_message'] = ['type' => 'error', 'text' => 'Fehler beim Duplizieren: ' . $e->getMessage()];
        }
        
        // Build redirect URL manually to avoid &amp; escaping
        $redirectUrl = 'index.php?page=yform/manager/table_field&table_name=' . urlencode($table->getTableName());
        rex_response::sendRedirect($redirectUrl);
        exit;
    }

    public static function ext_yformManagerRexInfo(rex_extension_point $ep): void
    {
        $manager = rex::getProperty('yform_usability.searchTableManager');
        $search = rex::getProperty('yform_usability.search');
        if ($manager && $search) {
            $content = $ep->getSubject();

            // Trefferzahl nur bei aktiver Suche; getDataListQuery wendet über YFORM_DATA_LIST_QUERY die Suche an
            $count = null;
            if ($search->state()->isActive()) {
                $query = $manager->getDataListQuery($manager->table->query()->alias('t0'), rex_request('rex_yform_filter', 'array', []));
                $count = $query->count();
            }

            // search bar
            $fragment = new rex_fragment();
            $fragment->setVar('manager', $manager);
            $fragment->setVar('search', $search, false);
            $fragment->setVar('count', $count, false);
            $fragment->setVar('errors', rex::getProperty('yform_usability.searchErrors', []), false);
            $partial = $fragment->parse('yform_usability/search.php');

            $fragment = new rex_fragment();
            $fragment->setVar('body', $partial, false);
            $fragment->setVar('class', 'info');
            $content .= $fragment->parse('core/page/section.php');
            $ep->setSubject($content);
        }
    }

    public static function ext_yformManagerDataPage(rex_extension_point $ep): void
    {
        $manager = $ep->getSubject();
        $search = Search::forTable($manager->table);

        if ($search) {
            $functions = $manager->dataPageFunctions;
            $sIndex = array_search('search', $functions);

            if ($sIndex !== false) {
                rex::setProperty('yform_usability.searchTableManager', $manager);
                rex::setProperty('yform_usability.search', $search);
                unset($functions[$sIndex]);
                $functions[] = 'yform_search';
                $manager->setDataPageFunctions($functions);
                $manager->setLinkVars($search->linkVars());
                // gefilterte, sonst ausgeblendete Felder vor dem Aufbau der Liste einblenden
                rex::setProperty('yform_usability.revealedFields', $search->revealHiddenFields());
                $ep->setSubject($manager);
            }
        }
    }

    public static function ext_yformDataList(rex_extension_point $ep): void
    {
        $config    = Usability::getConfig();
        $list      = $ep->getSubject();
        $table     = $ep->getParam('table');
        $tableName = $table->getTableName();
        $isOpener  = rex_get('rex_yform_manager_opener', 'array', []);

        $list->addFormAttribute('class', 'table-responsive');
        self::moveRevealedColumns($list, $table);

        $hasDuplicate = ($config['duplicate_tables_all'] ?? '') === '|1|' || in_array(
            $tableName,
            explode(
                '|',
                trim($config['duplicate_tables'] ?? '', '|')
            )
        );
        $hasStatus    = ($config['status_tables_all'] ?? '') === '|1|' || in_array(
            $tableName,
            explode(
                '|',
                trim($config['status_tables'] ?? '', '|')
            )
        );
        $hasSorting   = ($config['sorting_tables_all'] ?? '') === '|1|' || in_array(
            $tableName,
            explode(
                '|',
                trim($config['sorting_tables'] ?? '', '|')
            )
        );

        $hasStatus    = rex_extension::registerPoint(
            new rex_extension_point(
                'yform/usability.addStatusToggle',
                $hasStatus,
                ['list' => $list, 'table' => $table]
            )
        );
        $hasSorting   = rex_extension::registerPoint(
            new rex_extension_point(
                'yform/usability.addDragNDropSort',
                $hasSorting,
                ['list' => $list, 'table' => $table]
            )
        );

        if ($hasStatus && count($table->getFields(['name' => 'status']))) {
            $list = self::addStatusToggle($list, $table);
        }
        if ($hasSorting && empty($isOpener) && count($table->getFields(['name' => 'prio']))) {
            $list = self::addDragNDropSort($list, $table);
        }
        
        // Add thumbnail formatting for mapped columns
        $list = self::addThumbnailFormatting($list, $table);
        
        $ep->setSubject($list);
    }


    public static function yform_data_list_action_buttons(rex_extension_point $ep)
    {
        // return early if yform version is 4.1 or higher
        if (rex_version::compare(rex_addon::get('yform')->getVersion(), '4.1.0', '>=')) {
            return $ep->getSubject();
        }

        $buttons        = $ep->getSubject();
        $table          = $ep->getParam('table');
        $default_config = rex_addon::get('yform_usability')->getProperty('default_config');
        $config         = rex_addon::get('yform_usability')->getConfig(null, $default_config);
        $isOpener  = rex_get('rex_yform_manager_opener', 'array', []);

        $hasDuplicate = ($config['duplicate_tables_all'] ?? '') === '|1|' || in_array(
            $table->getTableName(),
            explode(
                '|',
                trim($config['duplicate_tables'] ?? '', '|')
            )
        );

        if ($hasDuplicate && empty($isOpener) && $table->isGranted('EDIT', rex::getUser())) {
            $_csrf_key = $table->getCSRFKey();
            $token = rex_csrf_token::factory($_csrf_key)->getUrlParams();

            $params = array();
            $params['table_name'] = $table->getTableName();
            $params['rex_yform_manager_popup'] = '0';
            $params['_csrf_token'] = $token['_csrf_token'];
            $params['id'] = "___id___";
            $params['yfu-action'] = 'duplicate';

            $buttons["duplicate"] = '<a href="' . rex_url::currentBackendPage($params) . '"><i class="rex-icon fa-files-o"></i> duplizieren</a>';
        }
        return $buttons;
    }

    /**
     * Durch einen Filter eingeblendete Spalten direkt hinter die ID setzen und im Kopf kennzeichnen.
     */
    private static function moveRevealedColumns(rex_yform_list $list, rex_yform_manager_table $table): void
    {
        $label = rex_escape(rex_i18n::rawMsg('yform_usability.search_revealed_column'));
        foreach (array_reverse((array) rex::getProperty('yform_usability.revealedFields', [])) as $name) {
            $field = $table->getValueField($name);
            if (!$field || !in_array($name, $list->getColumnNames(), true)) {
                continue;
            }
            try {
                $list->setColumnPosition($name, $list->getColumnPosition('id') + 1);
            } catch (\InvalidArgumentException) {
                // ohne ID-Spalte bleibt die Spalte an ihrem Platz
            }
            $list->setColumnLayout($name, [
                '<th class="yfu-revealed">###VALUE### <i class="rex-icon fa-filter" aria-hidden="true" title="' . $label . '"></i><span class="sr-only">(' . $label . ')</span></th>',
                '<td class="yfu-revealed yform-table-field-value-' . rex_escape($field->getTypeName()) . ' yform-table-field-name-' . rex_escape($name) . '" data-title="###LABEL###">###VALUE###</td>',
            ]);
        }
    }

    public static function ext_yformDataListSql(rex_extension_point $ep): void
    {
        $query = $ep->getSubject();

        // Einzelabfrage des Managers (Datensatz öffnen, speichern, löschen) nicht filtern: Passt der Datensatz
        // nach dem Bearbeiten nicht mehr zur Suche, gälte er sonst als nicht vorhanden
        if (str_contains($query->getQuery(), '`' . $query->getTableAlias() . '`.`id` = :where')) {
            return;
        }

        $table = rex_yform_manager_table::get($query->getTableName());
        $search = $table ? Search::forTable($table) : null;

        if ($search && $search->state()->isActive()) {
            $errors = $search->apply($query);
            if ($errors) {
                rex::setProperty('yform_usability.searchErrors', $errors);
            }
            $ep->setSubject($query);
        }
    }

    public static function ext__dataUpdated(rex_extension_point $ep): void
    {
        $data    = $ep->getParam('data');
        $oldData = $ep->getParam('old_data');

        if (array_key_exists('status', $oldData) && $data->getValue('status') != $oldData['status']) {
            rex_extension::registerPoint(
                new rex_extension_point('YFORM_DATA_STATUS_CHANGED', null, $ep->getParams())
            );
        }
    }

    public static function ext__getStatusColumnOptions(rex_extension_point $ep): void
    {
        if (rex_addon::get('sprog')->isAvailable()) {
            $options = $ep->getSubject();
            if (!is_iterable($options)) {
                return;
            }

            foreach ($options as &$option) {
                $option = Wildcard::parse($option);
            }
            $ep->setSubject($options);
        }
    }
}
