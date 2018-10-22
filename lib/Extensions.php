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
        $default_config = \rex_addon::get('yform_usability')->getProperty('default_config');
        $config         = \rex_addon::get('yform_usability')->getConfig(null, $default_config);
        $is_opener      = rex_get('rex_yform_manager_opener', 'array');

        $has_duplicate = count((array) $config['duplicate_tables']) && (in_array('all', $config['duplicate_tables']) || in_array($table->getTableName(), $config['duplicate_tables']));
        $has_status    = count((array) $config['status_tables']) && (in_array('all', $config['status_tables']) || in_array($table->getTableName(), $config['status_tables']));
        $has_sorting   = count((array) $config['sorting_tables']) && (in_array('all', $config['sorting_tables']) || in_array($table->getTableName(), $config['sorting_tables']));

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
        $list->addColumn('func_duplication', '<div class="duplicator"><i class="rex-icon fa-files-o"></i>&nbsp;' . \rex_addon::get('yform_usability')->i18n('action.duplicate') . '</div>', count($list->getColumnNames()));
        $list->setColumnLabel('func_duplication', '');
        $list->setColumnParams('func_duplication', ['func' => 'duplicate', 'id' => '###id###', 'page' => 'yform/manager/yform-usability']);
        return $list;
    }
}