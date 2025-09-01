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
use rex_sql_column;
use rex_sql_index;
use rex_sql_table;

rex_sql_table::get(rex::getTable('yform_table'))
    ->ensureColumn(new rex_sql_column('list_amount', 'bigint'))
    ->ensureColumn(new rex_sql_column('createdate', 'datetime'))
    ->ensureColumn(new rex_sql_column('updatedate', 'datetime'))
    ->ensureColumn(new rex_sql_column('createuser', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('updateuser', 'varchar(191)'))
    ->alter();

rex_sql_table::get(rex::getTable('yform_field'))
    ->ensureColumn(new rex_sql_column('partial', 'varchar(191)'), 'choice_attributes')
    ->ensureColumn(new rex_sql_column('createdate', 'datetime'))
    ->ensureColumn(new rex_sql_column('updatedate', 'datetime'))
    ->ensureColumn(new rex_sql_column('createuser', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('updateuser', 'varchar(191)'))
    ->alter();

// Create thumbnail mappings table
rex_sql_table::get(rex::getTable('yform_usability_thumbnails'))
    ->ensureColumn(new rex_sql_column('id', 'int(11)', false, null, 'auto_increment'))
    ->ensureColumn(new rex_sql_column('table_name', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('column_name', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('thumb_size', 'varchar(255)', false, 'rex_thumbnail_default'))
    ->ensureColumn(new rex_sql_column('createdate', 'datetime'))
    ->ensureColumn(new rex_sql_column('updatedate', 'datetime', true))
    ->setPrimaryKey('id')
    ->ensureIndex(new rex_sql_index('table_column', ['table_name', 'column_name'], rex_sql_index::UNIQUE))
    ->ensure();

