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
use rex_fragment;
use rex_i18n;
use rex_list;
use rex_sql;
use rex_sql_exception;
use rex_url;
use rex_view;
use rex_yform_manager_table;

$addon = rex_addon::get('yform_usability');

echo rex_view::title(rex_i18n::msg('yform') . ' - ' . $addon->i18n('media_mappings'));

$func = rex_request('func', 'string');
$table_name = rex_request('table_name', 'string');

// Handle form submissions
if ($func == 'add' || $func == 'edit') {
    $id = rex_request('id', 'int');
    
    if (rex_post('submit', 'boolean')) {
        $table_name = rex_post('table_name', 'string');
        $column_name = rex_post('column_name', 'string');
        $thumb_size = rex_post('thumb_size', 'string', 'rex_thumbnail_default');
        
        $sql = rex_sql::factory();
        
        if ($func == 'add') {
            // Check if mapping already exists
            $sql->setTable(rex::getTable('yform_usability_thumbnails'));
            $sql->setWhere('table_name = :table AND column_name = :column', [
                'table' => $table_name,
                'column' => $column_name
            ]);
            
            if ($sql->getRows() > 0) {
                echo rex_view::error(rex_i18n::msg('yform_usability.media_mapping_exists'));
            } else {
                $sql->reset();
                $sql->setTable(rex::getTable('yform_usability_thumbnails'));
                $sql->setValue('table_name', $table_name);
                $sql->setValue('column_name', $column_name);
                $sql->setValue('thumb_size', $thumb_size);
                $sql->setValue('createdate', date('Y-m-d H:i:s'));
                
                try {
                    $sql->insert();
                    echo rex_view::success(rex_i18n::msg('yform_usability.media_mapping_added'));
                    $func = '';
                } catch (rex_sql_exception $e) {
                    echo rex_view::error($e->getMessage());
                }
            }
        } else {
            $sql->setTable(rex::getTable('yform_usability_thumbnails'));
            $sql->setWhere('id = :id', ['id' => $id]);
            $sql->setValue('table_name', $table_name);
            $sql->setValue('column_name', $column_name);
            $sql->setValue('thumb_size', $thumb_size);
            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            
            try {
                $sql->update();
                echo rex_view::success(rex_i18n::msg('yform_usability.media_mapping_updated'));
                $func = '';
            } catch (rex_sql_exception $e) {
                echo rex_view::error($e->getMessage());
            }
        }
    }
    
    if (rex_post('delete', 'boolean') && $func == 'edit') {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('yform_usability_thumbnails'));
        $sql->setWhere('id = :id', ['id' => $id]);
        
        try {
            $sql->delete();
            echo rex_view::success(rex_i18n::msg('yform_usability.media_mapping_deleted'));
            $func = '';
        } catch (rex_sql_exception $e) {
            echo rex_view::error($e->getMessage());
        }
    }
}

if ($func == 'add' || $func == 'edit') {
    $id = rex_request('id', 'int');
    
    // Get form data
    $form_table_name = '';
    $form_column_name = '';
    $form_thumb_size = 'rex_thumbnail_default';
    
    if ($func == 'edit' && $id) {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('yform_usability_thumbnails'));
        $sql->setWhere('id = :id', ['id' => $id]);
        $sql->select();
        
        if ($sql->getRows()) {
            $form_table_name = $sql->getValue('table_name');
            $form_column_name = $sql->getValue('column_name');
            $form_thumb_size = $sql->getValue('thumb_size');
        }
    }
    
    // Get all YForm tables
    $tables = [];
    foreach (rex_yform_manager_table::getAll() as $table) {
        $tables[$table->getTableName()] = $table->getName() . ' (' . $table->getTableName() . ')';
    }
    asort($tables);
    
    // Get available thumbnail sizes (Media Manager types)
    $thumb_sizes = [];
    if (rex_addon::get('media_manager')->isAvailable()) {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT name FROM ' . rex::getTable('media_manager_type') . ' ORDER BY name');
        while ($sql->hasNext()) {
            $type_name = $sql->getValue('name');
            $thumb_sizes[$type_name] = $type_name;
            $sql->next();
        }
    }
    
    // Add default thumbnail option
    $thumb_sizes = array_merge(['rex_thumbnail_default' => rex_i18n::msg('yform_usability.default_thumbnail')], $thumb_sizes);
    
    echo '<div class="rex-addon-output">';
    echo '<div class="rex-form" id="rex-form-yform-usability-thumbnail">';
    echo '<form action="' . rex_url::currentBackendPage() . '" method="post">';
    echo '<input type="hidden" name="func" value="' . $func . '">';
    if ($func == 'edit') {
        echo '<input type="hidden" name="id" value="' . $id . '">';
    }
    
    echo '<fieldset>';
    echo '<legend>' . rex_i18n::msg($func == 'add' ? 'yform_usability.add_media_mapping' : 'yform_usability.edit_media_mapping') . '</legend>';
    
    echo '<div class="rex-form-row">';
    echo '<p class="rex-form-col-a rex-form-select">';
    echo '<label for="rex-yform-usability-table">' . rex_i18n::msg('yform_usability.table') . '</label>';
    echo '<select class="form-control" name="table_name" id="rex-yform-usability-table">';
    echo '<option value="">' . rex_i18n::msg('yform_usability.select_table') . '</option>';
    foreach ($tables as $table_key => $table_label) {
        $selected = $table_key == $form_table_name ? ' selected="selected"' : '';
        echo '<option value="' . htmlspecialchars($table_key) . '"' . $selected . '>' . htmlspecialchars($table_label) . '</option>';
    }
    echo '</select>';
    echo '</p>';
    echo '</div>';
    
    echo '<div class="rex-form-row">';
    echo '<p class="rex-form-col-a rex-form-text">';
    echo '<label for="rex-yform-usability-column">' . rex_i18n::msg('yform_usability.column') . '</label>';
    echo '<input class="form-control" type="text" name="column_name" id="rex-yform-usability-column" value="' . htmlspecialchars($form_column_name) . '">';
    echo '<span class="rex-form-notice">' . rex_i18n::msg('yform_usability.column_help') . '</span>';
    echo '</p>';
    echo '</div>';
    
    echo '<div class="rex-form-row">';
    echo '<p class="rex-form-col-a rex-form-select">';
    echo '<label for="rex-yform-usability-thumbsize">' . rex_i18n::msg('yform_usability.thumbnail_size') . '</label>';
    echo '<select class="form-control" name="thumb_size" id="rex-yform-usability-thumbsize">';
    foreach ($thumb_sizes as $size_key => $size_label) {
        $selected = $size_key == $form_thumb_size ? ' selected="selected"' : '';
        echo '<option value="' . htmlspecialchars($size_key) . '"' . $selected . '>' . htmlspecialchars($size_label) . '</option>';
    }
    echo '</select>';
    echo '</p>';
    echo '</div>';
    
    echo '<div class="rex-form-row">';
    echo '<p class="rex-form-col-a rex-form-submit">';
    echo '<input class="btn btn-save rex-form-aligned" type="submit" name="submit" value="' . rex_i18n::msg('yform_usability.save') . '">';
    if ($func == 'edit') {
        echo '<input class="btn btn-delete" type="submit" name="delete" value="' . rex_i18n::msg('yform_usability.delete') . '" onclick="return confirm(\'' . rex_i18n::msg('yform_usability.really_delete') . '\')">';
    }
    echo '<a class="btn btn-abort" href="' . rex_url::currentBackendPage() . '">' . rex_i18n::msg('yform_usability.abort') . '</a>';
    echo '</p>';
    echo '</div>';
    
    echo '</fieldset>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
    
    // Add JavaScript for dynamic column loading
    echo '<script>
    jQuery(document).ready(function($) {
        $("#rex-yform-usability-table").change(function() {
            var tableName = $(this).val();
            var $columnSelect = $("#rex-yform-usability-column");
            
            if (tableName) {
                // Here you could add AJAX to load column names dynamically
                // For now, it\'s a text input where users can type the column name
            }
        });
    });
    </script>';

} else {
    // List view
    
    // Add button
    echo '<div class="rex-addon-output">';
    echo '<div class="rex-toolbar">';
    echo '<div class="rex-toolbar-content">';
    echo '<a class="btn btn-primary" href="' . rex_url::currentBackendPage(['func' => 'add']) . '">';
    echo '<i class="rex-icon rex-icon-add-action"></i> ' . rex_i18n::msg('yform_usability.add_media_mapping');
    echo '</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Create list
    $list = rex_list::factory('SELECT id, table_name, column_name, thumb_size, createdate, updatedate FROM ' . rex::getTable('yform_usability_thumbnails') . ' ORDER BY table_name, column_name');
    
    $list->addTableAttribute('class', 'table-striped table-hover');
    
    $list->removeColumn('id');
    
    $list->setColumnLabel('table_name', rex_i18n::msg('yform_usability.table'));
    $list->setColumnLabel('column_name', rex_i18n::msg('yform_usability.column'));
    $list->setColumnLabel('thumb_size', rex_i18n::msg('yform_usability.thumbnail_size'));
    $list->setColumnLabel('createdate', rex_i18n::msg('yform_usability.createdate'));
    $list->setColumnLabel('updatedate', rex_i18n::msg('yform_usability.updatedate'));
    
    // Format table name to show readable name
    $list->setColumnFormat('table_name', 'custom', function($params) {
        $table_name = $params['list']->getValue('table_name');
        $table = rex_yform_manager_table::get($table_name);
        if ($table) {
            return htmlspecialchars($table->getName()) . ' <small>(' . htmlspecialchars($table_name) . ')</small>';
        }
        return htmlspecialchars($table_name);
    });
    
    // Format dates
    $list->setColumnFormat('createdate', 'strftime', '%d.%m.%Y %H:%M');
    $list->setColumnFormat('updatedate', 'strftime', '%d.%m.%Y %H:%M');
    
    // Add edit link
    $list->addColumn('edit', '<i class="rex-icon rex-icon-edit"></i> ' . rex_i18n::msg('yform_usability.edit'), -1, ['<th class="rex-table-action" colspan="1">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams('edit', ['func' => 'edit', 'id' => '###id###']);
    
    echo $list->get();
}
