<?php

use yform\usability\Utils;

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
        if (!rex::isBackend() || !rex_backend_login::hasSession()) {
            exit;
        }

        $method  = rex_request('method', 'string', null);
        $_method = '__' . $method;

        if (!$method || !method_exists($this, $_method)) {
            throw new rex_api_exception("Method '{$method}' doesn't exist");
        }
        try {
            $this->$_method();
        } catch (ErrorException $ex) {
            throw new rex_api_exception($ex->getMessage());
        }
        $this->response['method'] = strtolower($method);
        return new rex_api_result($this->success, $this->response);
    }

    /**
     * Every URL pointing here is built with getUrlParams() and therefore
     * already carries the token (Utils::getStatusColumnParams(),
     * Extensions::addDragNDropSort(), boot.php). The core does not check it
     * unless this returns true.
     */
    protected function requiresCsrfProtection()
    {
        return true;
    }

    /**
     * Signature of the filter conditions a sortable row carries.
     *
     * The conditions are SQL, provided server-side by the extension point
     * `yform/usability.addDragNDropSort.filters`, written into the markup
     * and posted back by the browser. Only conditions this installation
     * rendered itself are accepted.
     */
    public static function filterSignature(string $filter): string
    {
        $key = (string) rex_config::get('yform_usability', 'filter_key', '');
        if ('' === $key) {
            $key = bin2hex(random_bytes(32));
            rex_config::set('yform_usability', 'filter_key', $key);
        }

        return hash_hmac('sha256', $filter, $key);
    }

    private function __changestatus()
    {
        $status = rex_post('status', 'string');
        $data_id = (int) rex_post('data_id', 'int');
        $table = rex_post('table', 'string');

        // Get the YForm table object
        $tableObject = rex_yform_manager_table::get($table);
        if (!$tableObject) {
            return;
        }
        if (!$tableObject->isGranted('EDIT', rex::requireUser())) {
            throw new rex_api_exception('Permission denied');
        }

        // Use the table's query method to get the record
        /** @var rex_yform_manager_dataset|null $record */
        $record = $tableObject->query()->findId($data_id);
        if ($record) {
            // For status changes, we want to bypass full validation to avoid issues with
            // be_manager_relation fields that have empty validation (Issue #166)
            
            // Use rex_sql directly to update only the status field
            $sql = rex_sql::factory();
            $sql->setTable($tableObject->getTableName());
            $sql->setWhere('id = :id', ['id' => $data_id]);
            $sql->setValue('status', $status);
            
            if ($sql->update()) {
                // Reload the record to get updated data
                $record = $tableObject->query()->findId($data_id);
                
                if ($record) {
                    // NOTE: needed because on yorm save we cannot detect, if the status has changed
                    // (old data always same as live data)
                    // @see https://github.com/yakamara/redaxo_yform/issues/1443
                    rex_extension::registerPoint(
                        new rex_extension_point(
                            'YFORM_DATA_STATUS_CHANGED',
                            null,
                            [
                                'data_id' => $data_id,
                                'table' => $tableObject,
                                'data' => $record->getData(),
                                'old_data' => true,
                            ]
                        )
                    );
                }
            }
        }

        // flush url path file for url < 2
        if (rex_addon::get('url')->isAvailable()) {
            if (rex_version::compare(rex_addon::get('url')->getVersion(), '2.0.0', '<')) {
                rex_file::delete(rex_path::addonCache('url', 'pathlist.php'));
            }
        }

        $tparams = Utils::getStatusColumnParams($tableObject, $status);

        $tparams['element'] = strtr(
            $tparams['element'],
            [
                '{{ID}}' => $data_id,
                '{{TABLE}}' => $table,
            ]
        );
        $this->response = array_merge($this->response, $tparams);
    }

    private function __updatesort()
    {
        $tablename = rex_post('table', 'string');

        if ($tablename != '') {
            $sql     = rex_sql::factory();
            $data_id = rex_post('data_id', 'int');
            $next_id = rex_post('next_id', 'int');
            $filter  = rex_post('filter', 'string');

            // The filter conditions are SQL and come back from the browser:
            // only accept what this installation signed when rendering the list.
            if (!hash_equals(self::filterSignature($filter), rex_get('filter_sig', 'string', ''))) {
                throw new rex_api_exception('Invalid filter signature');
            }

            $filter  = strlen($filter) ? explode(',', $filter) : [];

            if (rex_post('table_type') == 'db_table') {
                // Sorting the fields of a table is part of the table manager,
                // which is admin only (yform/package.yml: table_field).
                if (!rex::requireUser()->isAdmin()) {
                    throw new rex_api_exception('Permission denied');
                }

                $sql       = rex_sql::factory();
                $filter[]  = 'table_name = ' . $sql->escape($tablename);
                $tablename = rex::getTable('yform_field');
                $sort      = 'desc' === rex_post('table_sort_order', 'string', 'asc') ? 'desc' : 'asc';
                // Interpolated into SQL below. The only value ever rendered
                // is "prio" (Extensions::ext_rexListGet()).
                $sfield    = rex_post('table_sort_field', 'string', 'prio');
                if ('prio' !== $sfield) {
                    throw new rex_api_exception('Invalid sort field');
                }


                if ($next_id) {
                    $sql->setTable($tablename);
                    $sql->setWhere('id = :id', ['id' => $next_id]);
                    $sql->select($sfield);
                    $prio = @$sql->getValue($sfield);
                } else {
                    $prio = @$sql->getArray(
                        "
                        SELECT {$sfield}
                        FROM {$tablename}
                        ORDER BY {$sfield} " . ($sort == 'asc' ? 'desc' : 'asc') . "
                        LIMIT 1
                    "
                    )[0]['prio'];
                }
                rex_yform_manager_table::deleteCache();
            } else {
                $tableobject = rex_yform_manager_table::get($tablename);
                if (!$tableobject) {
                    throw new rex_api_exception("Table '{$tablename}' not found");
                }
                if (!$tableobject->isGranted('EDIT', rex::requireUser())) {
                    throw new rex_api_exception('Permission denied');
                }
                $sort = strtolower($tableobject->getSortOrderName());

                if ($next_id) {
                    $prio = $tableobject->query()->findId($next_id)->getValue('prio');
                } else {
                    // Check if we have pagination context from frontend
                    $currentPageStart = (int) rex_post('current_page_start', 'int', 0);
                    $currentPageAmount = (int) rex_post('current_page_amount', 'int', 0);
                    
                    if ($currentPageAmount > 0) {
                        // We have pagination context - get the priority that should be after the last item on current page
                        // First, get all items with proper filtering and ordering
                        $query = $tableobject->query()->orderBy('prio', $sort);
                        
                        // Apply existing filters if any
                        if (!empty($filter)) {
                            foreach ($filter as $filterCondition) {
                                $query->whereRaw($filterCondition);
                            }
                        }
                        
                        $allItems = $query->find();
                        
                        if (!empty($allItems)) {
                            // Calculate which item should come after the current page's last position
                            $targetIndex = $currentPageStart + $currentPageAmount - 1; // -1 because we want the last item of current page
                            
                            if ($targetIndex < count($allItems)) {
                                // Use the priority of the item that's currently at the target position
                                $targetItem = $allItems[$targetIndex];
                                $prio = $targetItem->getValue('prio');
                            } else {
                                // Element should go to absolute end - get the highest priority
                                $lastItem = end($allItems);
                                $prio = $lastItem->getValue('prio');
                            }
                        } else {
                            // Fallback to original behavior if no items found
                            $prio = $tableobject->query()->orderBy('prio', $sort == 'asc' ? 'desc' : 'asc')->findOne()->getValue('prio');
                        }
                    } else {
                        // No pagination context - use original behavior (last item of entire table)
                        $prio = $tableobject->query()->orderBy('prio', $sort == 'asc' ? 'desc' : 'asc')->findOne()->getValue('prio');
                    }
                }
            }
            // Read from the database, but interpolated into SQL: make sure it is a number.
            $prio = (int) $prio;

            try {
                $query = "
                        UPDATE {$tablename}
                        SET prio = {$prio}
                        WHERE id = :id
                    ";
                $sql->setQuery($query, ['id' => $data_id]);

                if (!is_null($sql->getError())) {
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

                if (!is_null($sql->getError())) {
                    throw new rex_api_exception($sql->getError());
                }
            } catch (rex_sql_exception $ex) {
                throw new rex_api_exception($ex->getMessage());
            }
        }
    }
}
