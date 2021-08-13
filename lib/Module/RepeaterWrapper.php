<?php

/**
 * This file is part of the Kreatif\Project package.
 *
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 12.08.21
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability\Module;


use rex_request;


class RepeaterWrapper
{
    private int   $sliceValueId;
    private array $yformParams;
    private int   $repetitions;
    private array $fields = [];


    public function __construct(Form $form, int $sliceValueId, array $yformParams)
    {
        $this->sliceValueId = $sliceValueId;
        $this->yformParams  = $yformParams;

        $wrappers                = rex_session('yform_usability_repeaterWrappers', 'array', []);
        $wrappers[$sliceValueId] = $wrappers[$sliceValueId] ?? [];
        $values                  = (array)$form->getValuesFromSliceValueId($sliceValueId);

        if (Form::isRequestForRepeater()) {
            $action   = rex_request::get('action', 'string');
            $rowCount = (int)$wrappers[$sliceValueId]['repetitions'];
        } else {
            $rowCount = count($values);
        }

        $wrappers[$sliceValueId]['repetitions'] = $rowCount;
        rex_set_session('yform_usability_repeaterWrappers', $wrappers);

        $this->repetitions = $rowCount > 0 ? $rowCount : 1;
    }

    public function getSliceValueId()
    {
        return $this->sliceValueId;
    }

    public function incrementRepetitions()
    {
        $this->repetitions++;
        $wrappers = rex_session('yform_usability_repeaterWrappers', 'array', []);

        $wrappers[$this->sliceValueId]['repetitions'] = $this->repetitions;
        rex_set_session('yform_usability_repeaterWrappers', $wrappers);
    }

    public function decreaseRepetitions()
    {
        $this->repetitions--;
        $wrappers = rex_session('yform_usability_repeaterWrappers', 'array', []);

        $wrappers[$this->sliceValueId]['repetitions'] = $this->repetitions;
        rex_set_session('yform_usability_repeaterWrappers', $wrappers);
    }

    public function setValueField(array $fieldParams)
    {
        $fieldParams['valueId']        = $this->sliceValueId;
        $fieldParams['params']['name'] = "{$this->sliceValueId}.{{INDEX}}.{$fieldParams['name']}";
        $this->fields[]                = $fieldParams;
    }

    public function appendFieldsToForm(Form $form)
    {
        for ($i = 0; $i < $this->repetitions; $i++) {
            if ($i > 0) {
                // add the separator ytemplate partial between the repeated blocks
                $_separatorParams                  = $this->yformParams;
                $_separatorParams['name']          = "separator-{$_separatorParams['name']}";
                $_separatorParams['partial']       = 'separator';
                $_separatorParams['repeaterIndex'] = $i;
                $form->addValueField(
                    $this->yformParams['type'],
                    $this->sliceValueId,
                    $this->yformParams['name'],
                    $_separatorParams
                );
            }

            foreach ($this->fields as $field) {
                $field['params']['name'] = str_replace('{{INDEX}}', $i, $field['params']['name']);
                $form->addValueField(
                    $field['type'],
                    $field['valueId'],
                    $field['name'],
                    $field['params'],
                    $i
                );
            }
            $this->addSettingsField($form, $i);
        }

        // add the "end" ytemplate partial between the repeated blocks
        $endParams            = $this->yformParams['params'];
        $endParams['name']    = "end-{$this->yformParams['name']}";
        $endParams['partial'] = 'end';
        $form->addValueField($this->yformParams['type'], $this->sliceValueId, $this->yformParams['name'], $endParams);
    }

    private function addSettingsField(Form $form, int $repeaterIndex)
    {
        $form->addValueField(
            'hidden_text',
            $this->sliceValueId,
            '__settings',
            [
                'name'    => "{$this->sliceValueId}.{$repeaterIndex}.__settings",
                'default' => '{"status":1}',
            ],
            $repeaterIndex
        );
    }


    public static function ext__loadedData(\rex_extension_point $ep)
    {
        if (Form::isRequestForRepeater()) {
            $action     = rex_request::get('action', 'string');
            $sliceValue = rex_request::get('sliceValue', 'int');
            /** @var Form $form */
            $form = $ep->getParam('form');
            /** @var self $wrapper */
            $wrapper = $form->getFieldByName("repeater-{$sliceValue}")['wrapper'];

            if ($wrapper) {
                $loadedData = $ep->getSubject();
                parse_str(rex_request::get('formData', 'string'), $_data);

                foreach ($_data as $key => $value) {
                    if (substr($key, 0, 6) == 'form--') {
                        preg_match('!^form--(\d+)-(\d*)-?(.*)!', $key, $matches);

                        if ('' == $matches[2]) {
                            $loadedData[$matches[1]][$matches[3]] = $value;
                        } else {
                            $loadedData[$matches[1]][$matches[2]][$matches[3]] = $value;
                        }
                    }
                }

                if ('add-repeated-field' == $action) {
                    $wrapper->incrementRepetitions();
                    $index = rex_request::get('appendIndex', 'int');
                    array_splice(
                        $loadedData[$wrapper->getSliceValueId()],
                        $index,
                        0,
                        [['##new-value##', '__settings' => json_encode(['status' => 1])]]
                    );
                } elseif ('rm-repeated-field' == $action) {
                    $wrapper->decreaseRepetitions();
                    $index = rex_request::get('index', 'int');
                    array_splice($loadedData[$wrapper->getSliceValueId()], $index, 1);
                } elseif ('toggle-repeated-field-status' == $action) {
                    $index      = rex_request::get('index', 'int');
                    $nextStatus = rex_request::get('nextStatus', 'int');
                    $_settings  = &$loadedData[$wrapper->getSliceValueId()][$index]['__settings'];
                    $_settings  = json_decode($_settings, true);

                    $_settings['status'] = $nextStatus;

                    $_settings = json_encode($_settings);
                }
                $ep->setSubject($loadedData);
            }
        }
    }
}