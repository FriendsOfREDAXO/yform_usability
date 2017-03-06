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
        $status  = (int) rex_post('status', 'int');
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

        $this->response['new_status']     = $status ? 'online' : 'offline';
        $this->response['new_status_val'] = (int) !$status;
    }

    private function __updatesort()
    {
        $table = rex_post('table', 'string');

        if ($table != '') {
            $data_id = rex_post('data_id', 'int');
            $nid     = rex_post('next_id', 'int');
            $_filter = rex_post('filter', 'string');
            $Table   = rex_yform_manager_table::get($table);
            $class   = rex_yform_manager_dataset::getModelClass($table);
            $sort    = strtolower($Table->getSortOrderName());
            $sql     = \rex_sql::factory();
            $filter  = strlen($_filter) ? explode(',', $_filter) : [];
            $Object  = $nid ? $class::get($nid) : $class::query()->orderBy('prio', $sort == 'asc' ? 'desc' : 'asc')->findOne();
            $prio    = $Object->getValue('prio');

            try {
                $query = "
                        UPDATE {$table}
                        SET prio = {$prio}
                        WHERE id = :id
                    ";
                $sql->setQuery($query, ['id' => $data_id]);

                if (strlen($sql->getError())) {
                    throw new rex_api_exception($sql->getError());
                }
                $order = $nid ? ($sort == 'asc' ? '0, 1' : '1, 0') : ($sort == 'desc' ? '0, 1' : '1, 0');
                $where = count($filter) ? 'WHERE ' . implode(' AND ', $filter) : '';
                $query = "
                        UPDATE {$table}
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