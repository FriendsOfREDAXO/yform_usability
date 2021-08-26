<?php

/**
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 25.08.21
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability\lib\yform;



use yform\usability\lib\helpers\Csv;


class Export
{
    protected string $tableName          = '';
    protected array  $fields             = [];
    protected array  $fieldNames         = [];
    protected array  $fieldLabels        = [];
    protected array  $excludedFields     = [];
    protected array  $fullRelationFields = [];


    public function setTableName(string $tableName): void
    {
        $this->tableName = $tableName;
    }

    public function setExcludedFields(array $excludedFields): void
    {
        $this->excludedFields = $excludedFields;
    }

    public function addExcludedField(string $fieldName): void
    {
        if (!in_array($fieldName, $this->excludedFields)) {
            $this->excludedFields[] = $fieldName;
        }
    }

    protected function setFields(): void
    {
        $yTable = \rex_yform_manager_table::get($this->tableName);

        foreach ($yTable->getFields() as $field) {
            $isValid = 'value' == $field->getType();
            $isValid = $isValid && 'none' !== $field->getDatabaseFieldType();
            $isValid = $isValid && !in_array($field->getName(), $this->excludedFields);

            if ($isValid) {
                $this->fields[]      = $field;
                $this->fieldNames[]  = $field->getName();
                $this->fieldLabels[] = $field->getLabel();
            }
        }
    }

    protected function getQuery()
    {
        $this->setFields();
        /** @var $model \rex_yform_manager_dataset */
        $model = \rex_yform_manager_dataset::getModelClass($this->tableName);
        $query = $model::query();
        $query->alias('m');
        $query->resetSelect();

        foreach ($this->fields as $index => $field) {
            if ($field->getTypeName() == 'be_manager_relation' && !in_array(
                    $field->getName(),
                    $this->fullRelationFields
                )) {
                $query->leftJoin(
                    $field->getElement('table'),
                    "jt{$index}",
                    "jt{$index}.id",
                    "m.{$field->getName()}"
                );
                $query->selectRaw("CONCAT(jt{$index}.{$field->getElement('field')}, '')", $field->getName());
            } elseif ($field->getTypeName() == 'be_manager_relation') {
                $_tableName = $field->getElement('table');
                $_model     = \rex_yform_manager_dataset::getModelClass($_tableName);
                $_fields    = $_model::getAllYformFields();
                foreach ($_fields as $_field) {
                    if ($_field->getDatabaseFieldType() != 'none' && $_field->getTypeName(
                        ) != 'be_manager_relation' || $_field->getName() == 'id') {
                        $headers[] = Wildcard::parse($_field->getLabel());
                        $query->select(
                            'jtl' . $index . '.' . $_field->getName(),
                            $_tableName . '_' . $_field->getName()
                        );
                    }
                }
                $query->joinRelation($field->getName(), 'jtl' . $index);
            } elseif ($field->getTypeName() == 'choice') {
                $values = \rex_yform_value_choice::getListValues(
                    [
                        'field'  => $field->getName(),
                        'params' => ['field' => $field],
                    ]
                );

                $choiceValues[$field->getName()] = $values;
                $query->select("m.{$field->getName()}");
            } else {
                $query->select("m.{$field->getName()}");
            }
        }
        return $query;
    }

    public function getAsCSV(): Csv
    {
        $query      = $this->getQuery();
        $collection = $query->find();

        $csv = new Csv();
        $csv->setHeadColumns($this->fieldLabels);

        foreach ($collection as $item) {
            $csv->addRow($item->getData());
        }
        return $csv;
    }
}