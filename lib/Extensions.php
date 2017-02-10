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
    public static function yform_data_list($params)
    {
        $list  = $params->getSubject();
        $table = $params->getParam('table');
        $is_opener = rex_get('rex_yform_manager_opener', 'array');

        if (count($table->getFields(['name' => 'status'])) && \rex_extension::registerPoint(new \rex_extension_point('yform/usability.addStatusToggle', true, ['list' => $list, 'table' => $table])))
        {
            $list = self::addStatusToggle($list, $table);
        }
        if (empty ($is_opener) && count($table->getFields(['name' => 'prio'])) && \rex_extension::registerPoint(new \rex_extension_point('yform/usability.addDragNDropSort', true, ['list' => $list, 'table' => $table])))
        {
            $list = self::addDragNDropSort($list, $table);
        }
        return $list;
    }

    protected static function addStatusToggle($list, $table)
    {
        $list->addColumn('packing_list', '', count($list->getColumnNames()));
        $list->setColumnLabel('packing_list', 'Status');
        $list->setColumnFormat('packing_list', 'custom', function ($params)
        {
            $_status = $params['list']->getValue('status');
            $status  = $_status ? 'online' : 'offline';
            return '
                <a class="status-toggle rex-' . $status . '" 
                    data-id="' . $params['list']->getValue('id') . '" 
                    data-status="' . (int) !$_status . '"
                    data-table="' . $params['params']['table']->getTableName() . '"
                >
                    <i class="rex-icon rex-icon-' . $status . '"></i>
                    <span class="text">' . $status . '</span>
                </a>
            ';
        }, ['table' => $table]);
        return $list;
    }

    protected static function addDragNDropSort($list, $table)
    {
        $first_col_name = array_shift($list->getColumnNames());

        if ($first_col_name != 'id')
        {
            $list->addFormAttribute('class', 'sortable-list');
            $list->setColumnFormat($first_col_name, 'custom', function ($params)
            {
                $filters = \rex_extension::registerPoint(new \rex_extension_point('yform/usability.addDragNDropSort.filters', [], ['list_params' => $params]));
                return '
                        <i class="rex-icon fa fa-bars sort-icon" 
                            data-id="###id###" 
                            data-table="' . $params['params']['table']->getTableName() . '" 
                            data-sort="'. strtolower($params['params']['table']->getSortOrderName()) .'"
                            data-filter="'. implode(',', $filters) .'"></i>
                    ';
            }, ['table' => $table]);
        }
        return $list;
    }
}