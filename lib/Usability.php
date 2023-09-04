<?php

/**
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 30.03.21
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability;


use PDO;
use rex;
use rex_addon;
use rex_config;
use rex_sql;
use rex_sql_exception;
use rex_yform_manager_table;
use rex_yform_manager_table_api;

class Usability
{

    public static function init(): void
    {
        $action = rex_get('yfu-action', 'string');

        if ($action == 'duplicate') {
            self::duplicateRow();
        }
    }

    public static function includeAutoload()
    {
        require_once rex_addon::get('yform_usability')->getPath('vendor/autoload.php');
    }

    public static function getConfig($key = null)
    {
        return rex_config::get('yform_usability', $key);
    }

    public static function duplicateRow()
    {
        $id        = rex_get('id', 'int');
        $tablename = rex_get('table_name', 'string');
        $table = rex_yform_manager_table::get($tablename);
        if ($table->isGranted('EDIT', rex::getUser())) {
            if ($id > 0) {
                $sql = rex_sql::factory();
                $sql->setTable($tablename);
                $sql->setWhere('id = :id', ['id' => $id]);
                $sql->select();

                if ($sql->getRows()) {
                    $iSql = rex_sql::factory();
                    $iSql->setTable($tablename);
                    foreach ($sql->getFieldNames() as $field) {
                        if ($field == 'status') {
                            $iSql->setValue($field, 0);
                        } elseif ($field != 'id') {
                            $iSql->setValue($field, $sql->getValue($field));
                        }
                    }
                    $iSql->insert();
                }
            }
        }
    }

    public static function installTableSets($installPath)
    {
        $tablePrefix = rex::getTablePrefix();

        foreach (glob($installPath) as $filePath) {
            $filename  = basename($filePath);
            $tableName = rex::getTable(str_replace('.json', '', $filename));


            try {
                $tableCols = rex_sql::showColumns($tableName);
            } catch (rex_sql_exception $exception) {
                $sqlEx = $exception->getSql();
                // Error code 42S02 means: Table does not exist
                if ($sqlEx && $sqlEx->getErrno() === '42S02') {
                    $tableSet = str_replace('{{TABLE_PREFIX}}', $tablePrefix, file_get_contents($filePath));
                    rex_yform_manager_table_api::importTablesets($tableSet);
                } else {
                    throw $exception;
                }
            }
        }
    }

    public static function installTableStructure($installPath)
    {
        foreach (glob($installPath) as $file) {
            include_once $file;
        }
    }

    public static function installModules($installPath)
    {
        $sql = rex_sql::factory();

        foreach (glob($installPath) as $folder) {
            if (is_dir($folder)) {
                $_folders = explode('/', $folder);
                $name     = array_pop($_folders);
                $input    = file_get_contents(glob($folder . '/input.php')[0]);
                $output   = file_get_contents(glob($folder . '/output.php')[0]);
                $config   = json_decode(file_get_contents(glob($folder . '/config.json')[0]), true);

                if ($config && ($input || $output)) {
                    // check if already exists
                    $sql->setTable(rex::getTable('module'));
                    $sql->setWhere('`key` = :key OR `name` LIKE :name', ['key' => $config['key'], 'name' => $config['name']]);
                    $sql->select();
                    $_mod = $sql->getArray();

                    if (empty($_mod)) {
                        if ($input) {
                            $sql->setValue('input', $input);
                        }
                        if ($output) {
                            $sql->setValue('output', $output);
                        }
                        $sql->setTable('rex_module');
                        $sql->setValue('key', $config['key']);
                        $sql->setValue('name', $name);
                        $sql->insert();
                    }
                }
            }
        }
    }

    public static function ensureValueField($tableName, $fieldName, $typeName, $createValues, $updateValues = [])
    {
        if ('force-create' == rex_get('yfu-action', 'string')) {
            $updateValues = array_merge($createValues, $updateValues);
            $createValues = [];
        }
        self::ensureYformField('value', $tableName, $fieldName, $typeName, $createValues, $updateValues);
    }

    public static function ensureValidateField($tableName, $fieldName, $typeName, $createValues, $updateValues = [])
    {
        if ('force-create' == rex_get('yfu-action', 'string')) {
            $updateValues = array_merge($createValues, $updateValues);
            $createValues = [];
        }
        $updateValues['list_hidden'] = 1;
        $updateValues['search']      = 0;
        $updateValues['no_db']       = 0;
        $updateValues['label']       = '';
        $updateValues['db_type']     = '';
        self::ensureYformField('validate', $tableName, $fieldName, $typeName, $createValues, $updateValues);
    }

    private static function ensureYformField($fieldType, $tableName, $fieldName, $typeName, $createValues, $updateValues)
    {
        $sql   = rex_sql::factory();
        $query = "SELECT id FROM rex_yform_field WHERE name = :fname AND table_name = :tname AND type_id = '{$fieldType}'";


        if ($fieldType == 'validate') {
            $query                     .= " AND type_name = '{$typeName}'";
            $updateValues['type_name'] = $typeName;
        } else {
            $createValues['type_name'] = $typeName;
        }
        $fieldId = $sql->getArray(
            $query,
            [
                'tname' => $tableName,
                'fname' => $fieldName,
            ],
            PDO::FETCH_COLUMN
        );

        $sql->setTable('rex_yform_field');

        foreach ($updateValues as $key => $value) {
            $sql->setValue($key, $value);
        }
        if ($fieldId) {
            if (count($updateValues)) {
                $sql->setWhere(['id' => current($fieldId)]);
                $sql->update();
            }
        } else {
            foreach ($createValues as $key => $value) {
                $sql->setValue($key, $value);
            }
            $sql->setValue('table_name', $tableName);
            $sql->setValue('name', $fieldName);
            $sql->setValue('type_id', $fieldType);
            $sql->insert();
        }
    }

    public static function ensureStatusField($table, $prio, $default = 1)
    {
        self::ensureValueField(
            $table,
            'status',
            'choice',
            [
                'list_hidden' => 1,
                'search'      => 0,
                'label'       => 'translate:status',
                'prio'        => $prio++,
            ],
            [
                'db_type'  => 'int',
                'expanded' => 0,
                'multiple' => 0,
                'default'  => $default,
                'choices'  => json_encode(
                    [
                        'translate:active'   => 1,
                        'translate:inactive' => 0,
                    ]
                ),
            ]
        );
    }

    public static function ensurePriorityField($table, $prio)
    {
        self::ensureValueField(
            $table,
            'prio',
            'prio',
            [
                'label' => 'translate:priority',
                'prio'  => $prio++,
            ],
            [
                'db_type'     => 'int',
                'list_hidden' => 1,
                'search'      => 0,
            ]
        );
    }

    public static function ensureUserFields($table, &$prio)
    {
        self::ensureValueField(
            $table,
            'createuser',
            'be_user',
            [
                'label' => 'translate:created_by',
                'prio'  => $prio++,
            ],
            [
                'db_type'     => 'varchar(191)',
                'list_hidden' => 1,
                'search'      => 0,
                'only_empty'  => 1,
                'show_value'  => 1,
            ]
        );

        self::ensureValueField(
            $table,
            'updateuser',
            'be_user',
            [
                'label' => 'translate:updated_by',
                'prio'  => $prio++,
            ],
            [
                'db_type'     => 'varchar(191)',
                'list_hidden' => 1,
                'search'      => 0,
                'only_empty'  => 0,
                'show_value'  => 1,
            ]
        );
    }

    public static function ensureDateFields($table, &$prio)
    {
        self::ensureValueField(
            $table,
            'createdate',
            'datestamp',
            [
                'list_hidden' => 0,
                'search'      => 0,
                'show_value'  => 1,
                'label'       => 'translate:created_at',
                'prio'        => $prio++,
            ],
            [
                'db_type'    => 'datetime',
                'format'     => 'Y-m-d H:i:s',
                'only_empty' => 1,
                'no_db'      => 0,
            ]
        );

        self::ensureValueField(
            $table,
            'updatedate',
            'datestamp',
            [
                'list_hidden' => 1,
                'search'      => 0,
                'show_value'  => 1,
                'label'       => 'translate:updated_at',
                'prio'        => $prio++,
            ],
            [
                'db_type'    => 'datetime',
                'format'     => 'Y-m-d H:i:s',
                'only_empty' => 0,
                'no_db'      => 0,
            ]
        );
    }
}
