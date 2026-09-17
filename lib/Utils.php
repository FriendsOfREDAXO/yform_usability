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


use Exception;
use rex_api_yform_usability_api;
use rex_extension;
use rex_extension_point;
use rex_i18n;
use rex_url;
use rex_view;
use rex_yform_manager;
use rex_yform_manager_table;
use rex_yform_value_choice;

class Utils
{

    public static function parseBodyFromDataPage(rex_yform_manager $page)
    {
        try {
            ob_start();
            echo $page->getDataPage();
            $output = ob_get_clean();
            $output = preg_replace('/<header[^>]*>(.+?)<\/header>/is', '', $output);
        } catch (Exception $e) {
            ob_get_clean();
            $message = nl2br($e->getMessage() . "\n" . $e->getTraceAsString());
            $output  = rex_view::warning($message);
        }
        return $output;
    }

    /**
     * YForm-Core-Feldtypen, deren "choices"/"options"-Konfigurationselemente
     * dieselbe Bedeutung haben wie von getStatusColumnParams() erwartet
     * (Werteliste bzw. Wert=>Label-Zuordnung fuer die Status-Anzeige).
     */
    private const KNOWN_STATUS_FIELD_TYPES = ['choice', 'checkbox', 'select'];

    /**
     * True, wenn das "status"-Feld einer Tabelle von diesem AddOn selbst
     * dargestellt werden kann. Bewusst ueber eine Allowlist bekannter
     * YForm-Core-Feldtypen entschieden, NICHT ueber die blosse Anwesenheit
     * eines "choices"/"options"-Konfigurationselements: benutzerdefinierte
     * Feldtypen (z.B. ein eigener Inline-Switch aus dem "fields"-AddOn)
     * koennen ein gleichnamiges "options"-Element mit voellig anderer
     * Bedeutung fuehren (z.B. "0,1" als erlaubte Rohwerte statt als
     * Wert=>Label-Liste) - das wuerde sonst faelschlich als "bekannt"
     * durchgehen und die Spalte mit einem leeren/falschen Status-Icon
     * ueberschreiben, obwohl der eigentliche Feldtyp bereits ueber YForms
     * eigenen "rex_yform_value_<type>::getListValue()"-Mechanismus korrekt
     * gerendert wird. Eigene Erweiterungen koennen sich weiterhin ueber den
     * Extension Point "yform/usability.getStatusColumnParams.options"
     * anmelden, um zusaetzliche Feldtypen explizit zu unterstuetzen.
     */
    public static function hasKnownStatusOptions(rex_yform_manager_table $table): bool
    {
        $Field = $table->getValueField('status');
        if (null === $Field) {
            return false;
        }

        if (in_array($Field->getElement('type_name'), self::KNOWN_STATUS_FIELD_TYPES, true)) {
            return true;
        }

        $options = rex_extension::registerPoint(new rex_extension_point('yform/usability.getStatusColumnParams.options', [], [
            'table' => $table,
            'list'  => null,
        ]));

        return is_array($options) && count($options) > 0;
    }

    public static function getStatusColumnParams(rex_yform_manager_table $table, $currentValue, $list = null)
    {
        $Field = $table->getValueField('status');

        if (null === $Field) {
            return [
                'current_label' => '',
                'intern_status' => '',
                'toggle_value'  => '',
                'element'       => '',
            ];
        }

        $choices       = trim((string) $Field->getElement('choices'));
        $optionsString = trim((string) $Field->getElement('options'));
        $options       = [];
        $istatus       = '';

        if ('' !== $choices) {
            $options = rex_yform_value_choice::getListValues([
                'field'  => 'status',
                'params' => ['field' => $Field],
            ]);
        } else if ('' !== $optionsString) {
            $options = array_filter(Extensions::getArrayFromString($optionsString));
        } else if ($Field->getElement('type_name') == 'checkbox') {
            $options = [rex_i18n::msg('yrewrite_forward_inactive'), rex_i18n::msg('package_hactive')];
        }


        $options = rex_extension::registerPoint(new rex_extension_point('yform/usability.getStatusColumnParams.options', $options, [
            'table' => $table,
            'list'  => $list,
        ]));

        if (!is_array($options)) {
            $options = [];
        }
        $okeys   = count($options) ? array_keys($options) : explode(',', (string) $Field->getElement('values'));
        $cur_idx = array_search($currentValue, $okeys);
        if (false === $cur_idx || 0 === $cur_idx) {
            $cur_idx      = 0;
            $currentValue = $okeys[0];
        }
        $nvalue = $okeys[$cur_idx + 1] ?? $okeys[0];

        $url = rex_url::currentBackendPage(['method' => 'changeStatus'] + rex_api_yform_usability_api::getUrlParams());

        if (count($options) > 2) {
            $element = '<select class="form-control status-select rex-status-' . $currentValue . '" data-id="{{ID}}" data-api-url="'.$url . '" data-status="' . $nvalue . '" data-table="{{TABLE}}">';
            foreach ($options as $key => $option) {
                $element .= '<option value="' . $key . '" ' . ((string)$currentValue === (string)$key ? 'selected="selected"' : '') . '>' . rex_i18n::translate($option) . '</option>';
            }
            $element .= '</select>';
        } else if (count($options) == 1) {
            $element = array_shift($options);
        } else {
            $istatus = isset($options[$currentValue ?? '']) && $currentValue != 0 && $currentValue != '' ? 'online' : 'offline';
            $element = '
                <a class="rex-link-expanded status-toggle rex-' . $istatus . '" data-id="{{ID}}" data-api-url="'.$url . '" data-status="' . $nvalue . '" data-table="{{TABLE}}" href="#!">
                    <i class="rex-icon rex-icon-' . $istatus . '"></i>&nbsp;<span class="text">' . rex_i18n::translate($options[$currentValue ?? ''] ?? '') . '</span>
                </a>
            ';
        }

        return [
            'current_label' => $options[$currentValue ?? ''] ?? "",
            'intern_status' => $istatus,
            'toggle_value'  => $nvalue,
            'element'       => $element,
        ];
    }
}
