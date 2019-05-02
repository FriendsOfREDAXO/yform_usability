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


class Extensions
{
    protected static $manager   = null;
    protected static $hasSearch = false;

    public static function rex_list_get(\rex_extension_point $ep)
    {
        $list    = $ep->getSubject();
        $lparams = $list->getParams();
        $list->addFormAttribute('class', 'sortable-list');

        if ($lparams['page'] == 'yform/manager/table_field') {
            $first_col_name = $list->getColumnNames()[0];
            $table_name     = \rex_yform_manager_field::table();

            $list->setColumnLayout($first_col_name, ['<th class="rex-table-icon">###VALUE###</th>', '###VALUE###']); // ###VALUE###
            $list->setColumnFormat($first_col_name, 'custom', function ($params) {
                $filters = \rex_extension::registerPoint(new \rex_extension_point('yform/usability.addDragNDropSort.filters', [], ['list_params' => $params]));

                switch ($params['list']->getValue('type_id')) {
                    case 'validate':
                        $style = 'color:#aaa;'; // background-color:#cfd9d9;
                        break;
                    case 'action':
                        $style = 'background-color:#cfd9d9;';
                        break;
                    default:
                        $style = 'background-color:#eff9f9;';
                        break;
                }
                return '
                    <td class="rex-table-icon" style="' . $style . '">
                        <i class="rex-icon fa fa-bars sort-icon" 
                            data-id="###id###" 
                            data-table-type="db_table"
                            data-table-sort-field="prio"
                            data-table-sort-order="asc"
                            data-table="' . $params['params']['table'] . '" 
                            data-filter="' . implode(',', $filters) . '"></i>
                    </td>
                ';
            }, ['yform_table' => $lparams['table_name'], 'table' => $table_name]);
        }
    }

    public static function yform_data_list(\rex_extension_point $ep)
    {
        $list           = $ep->getSubject();
        $table          = $ep->getParam('table');
        $default_config = \rex_addon::get('yform_usability')
            ->getProperty('default_config');
        $config         = \rex_addon::get('yform_usability')
            ->getConfig(null, $default_config);
        $is_opener      = rex_get('rex_yform_manager_opener', 'array');

        $has_duplicate = count((array)$config['duplicate_tables']) && (in_array('all', $config['duplicate_tables']) || in_array($table->getTableName(), $config['duplicate_tables']));
        $has_status    = count((array)$config['status_tables']) && (in_array('all', $config['status_tables']) || in_array($table->getTableName(), $config['status_tables']));
        $has_sorting   = count((array)$config['sorting_tables']) && (in_array('all', $config['sorting_tables']) || in_array($table->getTableName(), $config['sorting_tables']));

        if ($has_duplicate && empty ($is_opener) && \rex_extension::registerPoint(new \rex_extension_point('yform/usability.addDuplication', true, ['list' => $list, 'table' => $table]))) {
            $list = self::addDuplication($list, $table);
        }
        if ($has_status && count($table->getFields(['name' => 'status'])) && \rex_extension::registerPoint(new \rex_extension_point('yform/usability.addStatusToggle', true, ['list' => $list, 'table' => $table]))) {
            $list = self::addStatusToggle($list, $table);
        }
        if ($has_sorting && empty ($is_opener) && count($table->getFields(['name' => 'prio'])) && \rex_extension::registerPoint(new \rex_extension_point('yform/usability.addDragNDropSort', true, ['list' => $list, 'table' => $table]))) {
            $list = self::addDragNDropSort($list, $table);
        }
        return $list;
    }

    public static function yform_data_list_sql(\rex_extension_point $ep)
    {
        $sql = $ep->getSubject();

        if (\rex_request('yfu-action', 'string') == 'search') {
            $where      = [];
            $table      = $ep->getParam('table');
            $sql_o      = \rex_sql::factory();
            $fields     = explode(',', \rex_request('yfu-searchfield', 'string'));
            $term       = trim(\rex_request('yfu-term', 'string'));
            $sprogIsAvl = \rex_addon::get('sprog')
                ->isAvailable();

            if ($term == '') {
                return $sql;
            }


            foreach ($fields as $fieldname) {
                $field = $table->getFields(['name' => $fieldname])[0];

                if ($field) {
                    if ($field->getTypename() == 'be_manager_relation') {
                        $relWhere  = [];
                        $query     = "
                            SELECT id
                            FROM {$field->getElement('table')}
                            WHERE {$field->getElement('field')} LIKE :term
                        ";
                        $relResult = $sql_o->getArray($query, ['term' => "%{$term}%"]);

                        foreach ($relResult as $item) {
                            $relWhere[] = $item['id'];
                        }

                        $relWhere = $relWhere ?: [-1];
                        $where[]  = $sql_o->escapeIdentifier($fieldname) . ' IN(' . implode(',', $relWhere) . ')';
                    } else if ($field->getTypename() == 'choice') {
                        $list    = new \rex_yform_choice_list([]);
                        $choices = $field->getElement('choices');

                        if (is_string($choices) && \rex_sql::getQueryType($choices) == 'SELECT') {
                            $list->createListFromSqlArray($sql_o->getArray($choices));
                        } else if (is_string($choices) && strlen(trim($choices)) > 0 && substr(trim($choices), 0, 1) == '{') {
                            $list->createListFromJson($choices);
                        } else {
                            $list->createListFromStringArray($self->getArrayFromString($choices));
                        }

                        foreach ($list->getChoicesByValues() as $value => $item) {
                            if (stripos($item, $term) !== false) {
                                $where[] = $sql_o->escapeIdentifier($fieldname) . ' = ' . $sql_o->escape($value);
                            } else if ($sprogIsAvl) {
                                $label = \Wildcard::get(strtr($item, [
                                    \Wildcard::getOpenTag()  => '',
                                    \Wildcard::getCloseTag() => '',
                                ]));

                                if (stripos($label, $term) !== false) {
                                    $where[] = $sql_o->escapeIdentifier($fieldname) . ' = ' . $sql_o->escape($value);
                                }
                            }
                        }
                        $where[] = $sql_o->escapeIdentifier($fieldname) . ' LIKE ' . $sql_o->escape('%' . $term . '%');
                    } else {
                        $where[] = $sql_o->escapeIdentifier($fieldname) . ' LIKE ' . $sql_o->escape('%' . $term . '%');
                    }
                } else if ($fieldname == 'id') {
                    $where[] = $sql_o->escapeIdentifier($fieldname) . ' = ' . $sql_o->escape($term);
                }
            }

            if (count($where)) {
                if (strrpos($sql, 'where') !== false) {
                    $sql = str_replace(' where ', ' where (' . implode(' OR ', $where) . ') AND ', $sql);
                } else {
                    $sql = str_replace(' ORDER BY ', ' WHERE (' . implode(' OR ', $where) . ') ORDER BY ', $sql);
                }
            }
        }

        return $sql;
    }

    public static function yform_manager_rex_info(\rex_extension_point $ep)
    {
        $content = $ep->getSubject();

        if (self::$hasSearch) {
            // search bar
            $fragment = new \rex_fragment();
            $fragment->setVar('manager', self::$manager);
            $partial = $fragment->parse('yform_usability/search.php');

            $fragment = new \rex_fragment();
            $fragment->setVar('body', $partial, false);
            $fragment->setVar('class', 'info');
            $content .= $fragment->parse('core/page/section.php');
        }
        return $content;
    }

    public static function yform_manager_data_page(\rex_extension_point $ep)
    {
        $manager = $ep->getSubject();

        if ($manager->table->isSearchable()) {
            $functions = $manager->dataPageFunctions;
            $sIndex    = array_search('search', $functions);

            if ($sIndex !== false) {
                self::$manager   = $manager;
                self::$hasSearch = true;
                unset($functions[$sIndex]);
                $functions[] = 'yform_search';
                $manager->setDataPageFunctions($functions);
            }
        }
        return $manager;
    }

    protected static function addStatusToggle($list, $table)
    {
        $list->addColumn('status_toggle', '', count($list->getColumnNames()));
        $list->setColumnLabel('status_toggle', 'Status');
        $list->setColumnFormat('status_toggle', 'custom', function ($params) {
            $value   = $params['list']->getValue('status');
            $tparams = Utils::getStatusColumnParams($params['params']['table'], $value, $params['list']);

            return strtr($tparams['element'], [
                '{{ID}}'    => $params['list']->getValue('id'),
                '{{TABLE}}' => $params['params']['table']->getTableName(),
            ]);
        }, ['table' => $table]);
        return $list;
    }

    protected static function addDragNDropSort($list, $table)
    {
        $columns        = $list->getColumnNames();
        $first_col_name = array_shift($columns);
        $orderBy        = rex_get('sort', 'string', $table->getSortFieldName());

        if ($first_col_name != 'id' && $orderBy == 'prio') {
            $list->addFormAttribute('class', 'sortable-list');
            $list->setColumnFormat($first_col_name, 'custom', function ($params) {
                $filters = \rex_extension::registerPoint(new \rex_extension_point('yform/usability.addDragNDropSort.filters', [], ['list_params' => $params]));
                return '
                        <i class="rex-icon fa fa-bars sort-icon" 
                            data-id="###id###" 
                            data-table-type="orm_model"
                            data-table="' . $params['params']['table']->getTableName() . '" 
                            data-filter="' . implode(',', $filters) . '"></i>
                    ';
            }, ['table' => $table]);
        }
        return $list;
    }

    protected static function addDuplication($list, $table)
    {
        $list->addColumn('func_duplication', '<div class="duplicator"><i class="rex-icon fa-files-o"></i>&nbsp;' . \rex_addon::get('yform_usability')
                ->i18n('action.duplicate') . '</div>', count($list->getColumnNames()));
        $list->setColumnLabel('func_duplication', '');
        $list->setColumnParams('func_duplication', ['func' => 'duplicate', 'id' => '###id###', 'page' => 'yform/manager/yform-usability']);
        return $list;
    }
}