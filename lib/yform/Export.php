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
    protected string                    $tableName          = '';
    protected ?\rex_yform_manager_table $yTable             = null;
    protected ?\rex_yform_manager_query $query              = null;
    protected array                     $fields             = [];
    protected array                     $fieldNames         = [];
    protected array                     $fieldLabels        = [];
    protected array                     $excludedFields     = [];
    protected array                     $fullRelationFields = [];


    public function __construct(string $tableName = '')
    {
        $this->tableName = $tableName;
    }

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

    public function addFullRelationField(string $fieldName, array $params = []): void
    {
        if (!isset($this->fullRelationFields[$fieldName])) {
            $this->fullRelationFields[$fieldName] = array_merge(
                [
                    'excludedFields' => [],
                ],
                $params
            );
        }
    }

    protected function setFields(): void
    {
        $this->yTable = \rex_yform_manager_table::get($this->tableName);
        $fields       = array_merge(
            [
                new \rex_yform_manager_field(
                    [
                        'type_id'   => 'value',
                        'type_name' => 'integer',
                        'name'      => 'id',
                        'label'     => 'ID',
                    ]
                ),
            ],
            $this->yTable->getFields()
        );

        foreach ($fields as $field) {
            $isValid = 'value' == $field->getType();
            $isValid = $isValid && 'none' !== $field->getDatabaseFieldType();
            $isValid = $isValid && !in_array($field->getName(), $this->excludedFields);

            if ($isValid) {
                $this->fields[]     = $field;
                $this->fieldNames[] = $field->getName();
            }
        }
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function setQuery(\rex_yform_manager_query $query)
    {
        $this->query = $query;
    }

    protected function getQuery()
    {
        if (!$this->query) {
            $this->setFields();
            /** @var $model \rex_yform_manager_dataset */
            $model       = \rex_yform_manager_dataset::getModelClass($this->tableName);
            $this->query = $model::query();
            $this->query->alias('m');
            $this->query->resetSelect();
            $this->query->resetOrderBy();
            $this->query->orderBy("m.{$this->yTable->getSortFieldName()}", $this->yTable->getSortOrderName());

            foreach ($this->fields as $index => $field) {
                $this->fieldLabels[] = $field->getLabel();

                if ($field->getTypeName() == 'be_manager_relation' && isset(
                        $this->fullRelationFields[$field->getName()]
                    )) {
                    $_class    = get_class($this);
                    $fullField = $this->fullRelationFields[$field->getName()];
                    $_export   = new $_class($field->getElement('table'));
                    $_export->setExcludedFields($fullField['excludedFields']);
                    $_export->getQuery();

                    array_pop($this->fieldLabels);

                    foreach ($_export->getFields() as $_field) {
                        $this->fieldLabels[] = $_field->getLabel();
                        $this->query->select(
                            'jtl' . $index . '.' . $_field->getName(),
                            $field->getElement('table') . '_' . $_field->getName()
                        );
                    }
                    $this->query->leftJoinRelation($field->getName(), 'jtl' . $index);
                } elseif ($field->getTypeName() == 'be_manager_relation') {
                    $this->query->leftJoin(
                        $field->getElement('table'),
                        "jt{$index}",
                        "jt{$index}.id",
                        "m.{$field->getName()}"
                    );
                    $this->query->selectRaw("CONCAT(jt{$index}.{$field->getElement('field')}, '')", $field->getName());
                } elseif ($field->getTypeName() == 'choice') {
                    $values = \rex_yform_value_choice::getListValues(
                        [
                            'field'  => $field->getName(),
                            'params' => ['field' => $field],
                        ]
                    );

                    $choiceValues[$field->getName()] = $values;
                    $this->query->select("m.{$field->getName()}");
                } else {
                    $this->query->select("m.{$field->getName()}");
                }
            }
        }
        return $this->query;
    }

    public function getAsArray(): array
    {
        $result     = [];
        $query      = $this->getQuery();
        $collection = $query->find();

        foreach ($collection as $item) {
            $result[] = $item->getData();
        }
        return $result;
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