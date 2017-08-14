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


class Utils
{
    public static function getStatusColumnParams(\rex_yform_manager_table $table, $currentValue)
    {
        $Field   = $table->getValueField('status');
        $options = (new \rex_yform_value_select())->getArrayFromString($Field->getElement('options'));
        
        if (!$currentValue) {
            $nvalue = 1;
            $intern_status = 'offline';
        }
        else {
            $nvalue = 0;
            $intern_status = 'online';
        }

        return [
            'current_label' => $options[$currentValue],
            'intern_status' => $currentValue > 0 ? 'online' : 'offline',
            'toggle_value'  => $nvalue,
        ];
    }
}
