<?php

/**
 * This file is part of the Kreatif\Project package.
 *
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 06.08.21
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability\Module;


use rex;
use rex_extension_point;
use rex_request;
use rex_sql;
use rex_var;
use rex_yform;
use yform\usability\Model\ArticleSlice;


class Form extends rex_yform
{
    protected int   $repeaterValueId   = 0;
    protected array $values            = [];
    protected array $loadedData        = [];
    protected array $formValues        = [];
    protected array $fields            = [];
    protected array $tabLabels         = [];
    protected bool  $collectTabContent = false;

    public function __construct(array $params = [])
    {
        $params['form_action']              = '';
        $params['csrf_protection']          = false;
        $params['submit_btn_show']          = false;
        $params['real_field_names']         = true;
        $params['form_showformafterupdate'] = true;
        $params['form_wrap_id']             = 'rex-yform-module-input';
        $params['form_ytemplate']           = 'backend,bootstrap,classic';

        parent::__construct($params);
    }

    public function getFieldByName(string $name)
    {
        return $this->fields[$name];
    }

    public static function isRequestForRepeater()
    {
        $action = rex_request::get('action', 'string');
        return rex_request::isXmlHttpRequest() && in_array(
                $action,
                [
                    'add-repeated-field',
                    'rm-repeated-field',
                    'toggle-repeated-field-status',
                ]
            );
    }

    public function getForm()
    {
        // close tab if forgotten by developer
        $this->closeTab();

        $tabSeparatorCount = 0;
        foreach ($this->fields as $field) {
            if ($field['fieldType'] == 'value') {
                if ($field['type'] == 'tab_wrapper') {
                    $field['params']['options'] = implode(',', $this->tabLabels);

                    if (!isset($field['params']['partial'])) {
                        $tabSeparatorCount++;
                        $field['params']['partial'] = $tabSeparatorCount == count(
                            $this->tabLabels
                        ) ? 'footer' : 'separator';
                    }
                }

                $this->addValueField($field['type'], $field['valueId'], $field['name'], $field['params']);

                if ($field['type'] == 'repeater_wrapper') {
                    $field['wrapper']->appendFieldsToForm($this);
                }
            }
        }

        $this->setObjectparams('data', empty($_POST) ? $this->formValues : false);
        rex_set_session('yform_usability_modyform', $this);
        $formOutput = parent::getForm();

        return $formOutput;
    }

    public function setValueField($type = '', $values = [])
    {
        [$valueId, $name] = explode('.', $values['name']);

        $fieldParams = [
            'fieldType' => 'value',
            'type'      => $type,
            'valueId'   => $valueId,
            'name'      => $name,
            'params'    => $values,
        ];

        if ($this->repeaterValueId > 0) {
            $wrapper = end($this->fields)['wrapper'];
            $wrapper->setValueField($fieldParams);
        } else {
            $this->fields[$name] = $fieldParams;
        }
    }

    public function addValueField($type, $valueId, $name, $params, $repeaterIndex = null)
    {
        $data = $this->getValuesFromSliceValueId($valueId);

        if ($repeaterIndex === null) {
            $_key  = "form--{$valueId}.{$name}";
            $value = $data[$name];
        } else {
            $_key  = "form--{$valueId}.{$repeaterIndex}.{$name}";
            $value = $data[$repeaterIndex][$name];
        }
        if (!isset($params['name'])) {
            $params['name'] = $name;
        }
        $params['values']  = $data;
        $params['valueId'] = $valueId;
        $params['name']    = "form--{$params['name']}";

        $this->formValues[$_key] = $value;
        parent::setValueField($type, $params);
    }

    public function getValuesFromSliceValueId(int $sliceValueId)
    {
        return $this->loadData($sliceValueId);
    }

    private function loadData(int $valueId)
    {
        if (!isset($this->loadedData[$valueId])) {
            $epString         = 'yform/usability/Module/Form.loadedData';
            $this->loadedData = \rex_extension::registerPoint(
                new \rex_extension_point($epString, $this->loadedData, ['sliceValueId' => $valueId, 'form' => $this])
            );

            if (!isset($this->loadedData[$valueId])) {
                $key     = "value{$valueId}";
                $sliceId = rex_request::get('slice_id', 'int');
                $query   = ArticleSlice::query();
                $query->select([$key]);
                $query->where('id', $sliceId);
                $collection = $query->findOne();
                $data       = $collection ? rex_var::toArray($collection->getValue($key)) : null;

                $this->loadedData[$valueId] = $data;
            }
        }
        return $this->loadedData[$valueId];
    }

    public function addTab(string $name)
    {
        if ($this->collectTabContent) {
            // close the previous tab
            $this->closeTab();
        } elseif (empty($this->tabLabels)) {
            $name = "tab-{$name}";

            $this->fields[$name] = [
                'fieldType' => 'value',
                'type'      => 'tab_wrapper',
                'valueId'   => 1,
                'name'      => $name,
                'params'    => ['partial' => 'head'],
            ];
        }

        $this->collectTabContent = true;
        $this->tabLabels[]       = $name;
    }

    public function closeTab()
    {
        if ($this->collectTabContent) {
            $name = 'tab-' . end($this->tabLabels);

            $this->fields[$name] = [
                'fieldType' => 'value',
                'type'      => 'tab_wrapper',
                'valueId'   => 1,
                'name'      => $name,
                'params'    => [],
            ];
        }
        $this->collectTabContent = false;
    }

    public function fieldRepeaterStart(int $sliceValueId): void
    {
        $params  = [
            'fieldType' => 'value',
            'type'      => 'repeater_wrapper',
            'valueId'   => $sliceValueId,
            'name'      => "repeater-{$sliceValueId}",
            'params'    => ['partial' => 'start', 'repeaterIndex' => 0],
        ];
        $wrapper = new RepeaterWrapper($this, $sliceValueId, $params);

        $params['wrapper']             = $wrapper;
        $this->fields[$params['name']] = $params;
        $this->repeaterValueId         = $sliceValueId;
    }

    public function repeaterEnd(): void
    {
        $this->repeaterValueId = 0;
    }

    public static function ext__updateSlice(rex_extension_point $ep): void
    {
        /** @var self $form */
        $form = rex_session('yform_usability_modyform');
        $form->getForm();

        if ($form->objparams['send']) {
            $values = [];
            $sql    = rex_sql::factory();
            $sql->setTable(rex::getTable('article_slice'));

            foreach ($form->objparams['value_pool']['sql'] as $key => $value) {
                [$valueId, $repeaterIndex, $name] = explode('.', substr($key, 6));

                if (isset($name)) {
                    $values[$valueId][$repeaterIndex][$name] = $value;
                } else {
                    $values[$valueId][$repeaterIndex] = $value;
                }
            }

            foreach ($values as $valueId => $data) {
                $sql->setValue("value{$valueId}", json_encode($data));
            }
            $sql->setWhere('id = :id', ['id' => $ep->getParam('slice_id')]);
            $sql->update();
        }
    }
}