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
    protected $published = TRUE;
    protected $success   = TRUE;

    public function execute()
    {
        $method  = rex_request('method', 'string', NULL);
        $_method = '__' . $method;

        if (!$method || !method_exists($this, $_method))
        {
            throw new rex_api_exception("Method '{$method}' doesn't exist");
        }
        try
        {
            $this->$_method();
        }
        catch (ErrorException $ex)
        {
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

        $sql
            ->setTable($table)
            ->setValue('status', $status)
            ->setWhere(['id' => $data_id]);
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
        $prio = rex_post('prio', 'int');

        if ($prio != 0)
        {
            $table   = rex_post('table', 'string');
            $data_id = rex_post('data_id', 'int');
            $_filter = rex_post('filter', 'string');
            $sql     = \rex_sql::factory();
            $_prio   = abs($prio);
            $filter  = strlen($_filter) ? explode(',', $_filter) : [];

            try
            {
                $query = "
                        UPDATE {$table}
                        SET prio = prio " . ($prio < 0 ? '-' : '+') . " {$_prio} 
                        WHERE id = :id
                    ";
                $sql->setQuery($query, ['id' => $data_id]);

                if (strlen($sql->getError()))
                {
                    throw new rex_api_exception($sql->getError());
                }

                $order = $prio < 0 ? '0, 1' : '1, 0';
                $where = count($filter) ? 'WHERE '. implode(' AND ', $filter) : '';
                $query = "
                        UPDATE {$table}
                        SET `prio` = (SELECT @count := @count + 1)
                        {$where}
                        ORDER BY `prio`, IF(`id` = :id, {$order})
                    ";
                $sql->setQuery('SET @count = 0');
                $sql->setQuery($query, ['id' => $data_id]);

                if (strlen($sql->getError()))
                {
                    throw new rex_api_exception($sql->getError());
                }
            }
            catch (\rex_sql_exception $ex)
            {
                throw new rex_api_exception($ex->getMessage());
            }
        }
    }
}