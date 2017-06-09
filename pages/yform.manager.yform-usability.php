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

$func  = rex_get('func', 'string');
$id    = rex_get('id', 'int');
$tablename = rex_get('table_name', 'string');

switch ($func) {
    case 'duplicate':
        $sql = \rex_sql::factory();
        $sql->setTable($tablename);
        $sql->setWhere('id = '.$id);
        $sql->select('*');
        if($sql->getRows())
        {
             $iSql = \rex_sql::factory();
             $iSql->setTable($tablename);
             foreach ($sql->getFieldNames() as $field) {
                 if ($field == 'status') {
                     $iSql->setValue($field, 0);
                 }
                 else if ($field != 'id') {
                     $iSql->setValue($field, $sql->getValue($field));
                 }
             }
             $iSql->insert();
        }
        break;
}
header('Location: '. \rex_url::backendPage('yform/manager/data_edit', ['table_name' => $tablename], false));
exit;