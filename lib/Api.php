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
class rex_api_yform_usability_api extends rex_api_function
{
    protected $response  = [];
    protected $published = true;
    protected $success   = true;

    public function execute()
    {
        $method  = rex_request('method', 'string', null);
        $_method = '__' . $method;

        if (!$method || !method_exists($this, $_method)) {
            throw new rex_api_exception("Method '{$method}' doesn't exist");
        }
        try {
            $this->$_method();
        }
        catch (ErrorException $ex) {
            throw new rex_api_exception($ex->getMessage());
        }
        $this->response['method'] = strtolower($method);
        return new rex_api_result($this->success, $this->response);
    }

    private function __changestatus()
    {
        $status  = rex_post('status', 'string');
        $data_id = (int) rex_post('data_id', 'int');
        $table   = rex_post('table', 'string');
        $sql     = rex_sql::factory();

        $sql->setTable($table)->setValue('status', $status)->setWhere(['id' => $data_id]);
        try {
            $sql->update();
        }
        catch (\rex_sql_exception $ex) {
            throw new rex_api_exception($ex->getMessage());
        }
        // flush url path file
        if (rex_addon::get('url')->isAvailable()) {
            rex_file::delete(rex_path::addonCache('url', 'pathlist.php'));
        }

        $tparams = \yform\usability\Utils::getStatusColumnParams(rex_yform_manager_table::get($table), $status);

        $tparams['element'] = strtr($tparams['element'], [
            '{{ID}}'    => $data_id,
            '{{TABLE}}' => $table,
        ]);
        $this->response = array_merge($this->response, $tparams);
    }

    private function __updatesort()
    {
        $tablename = rex_post('table', 'string');

        if ($tablename != '') {
            $data_id = rex_post('data_id', 'int');
            $next_id = rex_post('next_id', 'int');
            $filter  = rex_post('filter', 'string');
            $filter  = strlen($filter) ? explode(',', $filter) : [];

            if (rex_post('table_type') == 'db_table') {
                $sql    = rex_sql::factory();
                $sort   = rex_post('table_sort_order', 'string', 'asc');
                $sfield = rex_post('table_sort_field', 'string', 'prio');


                if ($next_id) {
                    $sql->setTable($tablename);
                    $sql->setWhere('id = :id', ['id' => $next_id]);
                    $sql->select($sfield);
                    $prio = @$sql->getValue($sfield);
                }
                else {
                    $prio = @$sql->getArray("
                        SELECT {$sfield} 
                        FROM {$tablename} 
                        ORDER BY {$sfield} ". ($sort == 'asc' ? 'desc' : 'asc') ." 
                        LIMIT 1
                    ")[0]['prio'];
                }
                rex_yform_manager_table::deleteCache();
            }
            else {
                $tableobject = rex_yform_manager_table::get($tablename);
                $sort        = strtolower($tableobject->getSortOrderName());

                if ($next_id) {
                    $prio = $tableobject->query()->findId($next_id)->getValue('prio');
                }
                else {
                    $prio = $tableobject->query()->orderBy('prio', $sort == 'asc' ? 'desc' : 'asc')->findOne()->getValue('prio');
                }
            }
            try {
                $query = "
                        UPDATE {$tablename}
                        SET prio = {$prio}
                        WHERE id = :id
                    ";
                $sql   = \rex_sql::factory();
                $sql->setQuery($query, ['id' => $data_id]);

                if (strlen($sql->getError())) {
                    throw new rex_api_exception($sql->getError());
                }
                $order = $next_id ? ($sort == 'asc' ? '0, 1' : '1, 0') : ($sort == 'desc' ? '0, 1' : '1, 0');
                $where = count($filter) ? 'WHERE ' . implode(' AND ', $filter) : '';
                $query = "
                        UPDATE {$tablename}
                        SET `prio` = (SELECT @count := @count + 1)
                        {$where}
                        ORDER BY `prio`, IF(`id` = :id, {$order})
                    ";
                $sql->setQuery('SET @count = 0');
                $sql->setQuery($query, ['id' => $data_id]);

                if (strlen($sql->getError())) {
                    throw new rex_api_exception($sql->getError());
                }
            }
            catch (\rex_sql_exception $ex) {
                throw new rex_api_exception($ex->getMessage());
            }
        }
    }

}