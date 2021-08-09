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


class ModYform extends rex_yform
{
    protected int   $repeaterValueId   = 0;
    protected array $values            = [];
    protected array $loadedData        = [];
    protected array $formValues        = [];
    protected array $fields            = [];
    protected array $repeatedFields    = [];
    protected array $tabLabels         = [];
    protected bool  $collectTabContent = false;

    public function __construct(array $params = [])
    {
        $params['form_action']              = '';
        $params['submit_btn_show']          = false;
        $params['form_showformafterupdate'] = true;
        $params['form_ytemplate']           = 'backend,bootstrap,classic';

        parent::__construct($params);
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
                    $amount = rex_request::get('rAmount', 'array')[$field['name']] ?? 1;

                    for ($i = 0; $i < $amount; $i++) {
                        if ($i > 0) {
                            $_separatorParams            = $field['params'];
                            $_separatorParams['name']    = "separator-{$_separatorParams['name']}";
                            $_separatorParams['partial'] = 'separator';
                            $this->addValueField($field['type'], $field['valueId'], $field['name'], $_separatorParams);
                        }

                        foreach ($this->repeatedFields[$field['valueId']] as $_field) {
                            $_field['params']['name'] = str_replace('{{INDEX}}', $i, $_field['params']['name']);
                            $this->addValueField(
                                $_field['type'],
                                $_field['valueId'],
                                $_field['name'],
                                $_field['params']
                            );
                        }
                    }

                    $_endParams            = $field['params'];
                    $_endParams['name']    = "end-{$_endParams['name']}";
                    $_endParams['partial'] = 'end';
                    $this->addValueField($field['type'], $field['valueId'], $field['name'], $_endParams);
                }
            }
        }

        $this->setObjectparams('data', empty($_POST) ? $this->formValues : false);
        rex_set_session('yform_usability_modyform', $this);
        return parent::getForm();
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
            $valueId                       = $this->repeaterValueId;
            $fieldParams['valueId']        = $valueId;
            $fieldParams['params']['name'] = "{$valueId}.{{INDEX}}.{$name}";
            //$values['default'] = $item[$name] ?? $values['default'];
            $this->repeatedFields[$this->repeaterValueId][] = $fieldParams;
        } else {
            $this->fields[] = $fieldParams;
        }
    }

    private function addValueField($type, $valueId, $name, $params, $repeaterIndex = null)
    {
        $data = $this->loadData($valueId);

        if ($repeaterIndex !== null) {
            $value = $data[$repeaterIndex][$name];
        } else {
            $value = $data[$name];
        }
        if (!isset($params['name'])) {
            $params['name'] = $name;
        }

        $this->formValues["{$valueId}.{$name}"] = $value;
        parent::setValueField($type, $params);
    }

    private function loadData(int $valueId)
    {
        if (!isset($this->loadedData[$valueId])) {
            $key     = "value{$valueId}";
            $sliceId = rex_request::get('slice_id', 'int');
            $query   = \yform\usability\Model\ArticleSlice::query();
            $query->select([$key]);
            $query->where('id', $sliceId);
            $this->loadedData[$valueId] = rex_var::toArray($query->findOne()->getValue($key));
        }
        return $this->loadedData[$valueId];
    }

    public function addTab(string $name)
    {
        if ($this->collectTabContent) {
            // close the previous tab
            $this->closeTab();
        } elseif (empty($this->tabLabels)) {
            $this->fields[] = [
                'fieldType' => 'value',
                'type'      => 'tab_wrapper',
                'valueId'   => 1,
                'name'      => 'tab-' . $name,
                'params'    => ['partial' => 'head'],
            ];
        }

        $this->collectTabContent = true;
        $this->tabLabels[]       = $name;
    }

    public function closeTab()
    {
        if ($this->collectTabContent) {
            $this->fields[] = [
                'fieldType' => 'value',
                'type'      => 'tab_wrapper',
                'valueId'   => 1,
                'name'      => 'tab-' . end($this->tabLabels),
                'params'    => [],
            ];
        }
        $this->collectTabContent = false;
    }

    public function fieldRepeaterStart(int $sliceValueId): void
    {
        $this->repeaterValueId = $sliceValueId;

        $this->fields[] = [
            'fieldType' => 'value',
            'type'      => 'repeater_wrapper',
            'valueId'   => $sliceValueId,
            'name'      => "repeater-{$sliceValueId}",
            'params'    => ['partial' => 'start'],
        ];
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
                [$valueId, $repeaterIndex, $name] = explode('.', $key);

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