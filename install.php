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

if (!$this->hasConfig()) {
    $config = [
        'status_tables'    => ['all'],
        'sorting_tables'   => ['all'],
        'duplicate_tables' => ['all'],
    ];
    $this->setConfig($config);

    // alter the yform table manager table
    $sql = \rex_sql::factory();
    if (count($sql->getArray("SHOW TABLES LIKE 'rex_yform_table'"))) {
        $sql->setQuery("ALTER TABLE `rex_yform_table` MODIFY `list_amount` INTEGER UNSIGNED");
    }
}

