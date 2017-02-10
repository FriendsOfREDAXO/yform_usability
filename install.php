<?php

/**
 * This file is part of the Shop package.
 *
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 09.02.17
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace yform\usability;


if (!$this->hasConfig())
{
    $this->setConfig('installed', TRUE);

    // alter the yform table manager table
    $sql = \rex_sql::factory();
    if (count($sql->getArray("SHOW TABLES LIKE 'rex_yform_table'")))
    {
        $sql->setQuery("ALTER TABLE `rex_yform_table` MODIFY `list_amount` INTEGER");
    }
}