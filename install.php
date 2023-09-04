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

