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

        $okeys   = array_keys($options);
        $cur_idx = array_search($currentValue, $okeys);
        $nvalue  = isset($okeys[$cur_idx + 1]) ? $okeys[$cur_idx + 1] : $okeys[0];
        $istatus = $currentValue > 1 ? 'status-' . $currentValue : ($currentValue > 0 ? 'online' : 'offline');

        if (count($options) > 2) {
            $element = '<select class="status-select rex-status-' . $currentValue . '" data-id="{{ID}}" data-status="' . $nvalue. '" data-table="{{TABLE}}">';

            foreach ($options as $key => $option) {
                $element .= '<option value="'. $key .'" '. ($currentValue == $key ? 'selected="selected"' : '') .'>'. $option .'</option>';
            }
            $element .= '</select>';
        }
        else {
            $element = '
                <a class="status-toggle rex-' . $istatus . '" data-id="{{ID}}" data-status="' . $nvalue. '" data-table="{{TABLE}}">
                    <i class="rex-icon rex-icon-' . $istatus . '"></i>&nbsp;<span class="text">' . $options[$currentValue] . '</span>
                </a>
            ';
        }

        return [
            'current_label' => $options[$currentValue],
            'intern_status' => $istatus,
            'toggle_value'  => $nvalue,
            'element'       => $element,
        ];
    }
}
