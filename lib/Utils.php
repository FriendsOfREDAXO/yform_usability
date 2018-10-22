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
    public static function getStatusColumnParams(\rex_yform_manager_table $table, $currentValue, $list = null)
    {
        $Field   = $table->getValueField('status');
        $choices = $Field->getElement('choices');
        $options = $Field->getElement('options');

        if (strlen(trim($choices))) {
            $options = \rex_yform_value_choice::getListValues([
                'field'  => 'status',
                'params' => ['field' => $Field],
            ]);
        } else if (strlen(trim($options))) {
            $options = array_filter((array)(new \rex_yform_value_select())->getArrayFromString($Field->getElement('options')));
        } else if ($Field->getElement('type_name') == 'checkbox') {
            $options = [\rex_i18n::msg('yrewrite_forward_inactive'), \rex_i18n::msg('package_hactive')];
        }


        $options = \rex_extension::registerPoint(new \rex_extension_point('yform/usability.getStatusColumnParams.options', $options, [
            'table' => $table,
            'list'  => $list,
        ]));

        $okeys   = count($options) ? array_keys($options) : explode(',', $Field->getElement('values'));
        $cur_idx = array_search($currentValue, $okeys);
        $nvalue  = isset($okeys[$cur_idx + 1]) ? $okeys[$cur_idx + 1] : $okeys[0];
        

        if (count($options) > 2) {
            $element = '<select class="status-select rex-status-' . $currentValue . '" data-id="{{ID}}" data-status="' . $nvalue . '" data-table="{{TABLE}}">';
            foreach ($options as $key => $option) {
                $element .= '<option value="' . $key . '" ' . ((string)$currentValue === (string)$key ? 'selected="selected"' : '') . '>' . \rex_i18n::translate($option) . '</option>';
            }
            $element .= '</select>';
        } else if (count($options) == 1) {
            $element = array_shift($options);
        } else {
            $istatus = isset($options[$currentValue]) && $currentValue != 0 && $currentValue != '' ? 'online' : 'offline';
            $element = '
                <a class="status-toggle rex-' . $istatus . '" data-id="{{ID}}" data-status="' . $nvalue . '" data-table="{{TABLE}}">
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
