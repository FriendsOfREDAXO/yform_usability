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

$config = array_merge([
    'installed'       => 1,
    'has_status'      => 1,
    'has_sorting'     => 1,
    'has_duplication' => 1,
], $this->getConfig(null, []));


if (!$this->hasConfig()) {
    // alter the yform table manager table
    $sql = \rex_sql::factory();
    if (count($sql->getArray("SHOW TABLES LIKE 'rex_yform_table'"))) {
        $sql->setQuery("ALTER TABLE `rex_yform_table` MODIFY `list_amount` INTEGER UNSIGNED");
    }
}

$this->setConfig($config);