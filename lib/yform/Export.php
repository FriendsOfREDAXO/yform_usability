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


use rex_clang;
use rex_extension;
use rex_extension_point;
use rex_i18n;
use rex_yform_manager_dataset;
use rex_yform_manager_field;
use rex_yform_manager_query;
use rex_yform_manager_table;
use rex_yform_value_choice;
use Sprog\Wildcard;
use yform\usability\lib\helpers\Csv;


class Export
{
    protected string                    $tableName          = '';
    protected ?rex_yform_manager_table $yTable             = null;
    protected ?rex_yform_manager_query $query              = null;
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
        $this->yTable = $this->query->getTable();
        $fields       = array_merge(
            [
                new rex_yform_manager_field(
                    [
                        'type_id'    => 'value',
                        'type_name'  => 'integer',
                        'name'       => 'id',
                        'label'      => 'ID',
                        'table_name' => $this->yTable->getTableName(),
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

    public function setQuery(rex_yform_manager_query $query)
    {
        $this->query = $query;
    }

    protected function getQuery()
    {
        if (!$this->query) {
            /** @var $model rex_yform_manager_dataset */
            $model       = rex_yform_manager_dataset::getModelClass($this->tableName);
            $this->query = $model::query();
        }

        $this->setFields();
        $this->query->alias('m');
        $this->query->resetSelect();
        $this->query->resetOrderBy();
        $this->query->orderBy("m.{$this->yTable->getSortFieldName()}", $this->yTable->getSortOrderName());

        foreach ($this->fields as $index => $field) {
            $fieldAlias                     = "{$this->tableName}___{$field->getName()}";
            $this->fieldLabels[$fieldAlias] = self::getFieldLabel($field);

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
                    if ('' == $_field->getElement('relation_table') || 1 != $_field->getElement('type')) {
                        if (!in_array($_field, $_export->excludedFields)) {
                            $_fieldAlias                     = "{$field->getElement('table')}___{$_field->getName()}";
                            $this->fieldLabels[$_fieldAlias] = self::getFieldLabel($_field);
                            $this->query->select('jtl' . $index . '.' . $_field->getName(), $_fieldAlias);
                        }
                    }
                }
                $this->query->leftJoinRelation($field->getName(), 'jtl' . $index);
            } elseif ($field->getTypeName() == 'be_manager_relation') {
                $this->query->leftJoin(
                    $field->getElement('table'),
                    "jt{$index}",
                    "jt{$index}.id",
                    "m.{$field->getName()}"
                );
                $_fieldAlias = "{$field->getElement('table')}___{$field->getName()}";
                $this->query->selectRaw("CONCAT(jt{$index}.{$field->getElement('field')}, '')", $_fieldAlias);
            } elseif ($field->getTypeName() == 'choice') {
                $values = rex_yform_value_choice::getListValues(
                    [
                        'field'  => $field->getName(),
                        'params' => ['field' => $field],
                    ]
                );

                $this->query->select("m.{$field->getName()}", $fieldAlias);
            } else {
                $this->query->select("m.{$field->getName()}", $fieldAlias);
            }
        }
        return $this->query;
    }

    private static function getFieldLabel($field): string
    {
        $currentTable = rex_yform_manager_table::get($field->getElement('table') ?: $field->getElement('table_name'));

        $tablePrefix  = is_object($currentTable) ? $currentTable->getName() : '';
        if (str_contains($tablePrefix, 'translate:')) {
            $tablePrefix = rex_i18n::translate($tablePrefix, false);
        }
        $fieldPrefix = $tablePrefix ? $tablePrefix . ': ' : '';

        return $fieldPrefix . Wildcard::parse($field->getLabel()) . self::getFieldLanguageSuffix($field->getName());
    }

    private static function getFieldLanguageSuffix($fieldName): string
    {
        $languages = rex_clang::getAll(true);
        foreach ($languages as $language) {
            if (array_reverse(explode('_', $fieldName))[0] == $language->getId()) {
                return " ({$language->getCode()})";
            }
        }
        return '';
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

        $csvHeadColumns = array_filter(
            rex_extension::registerPoint(
                new rex_extension_point('yform/usability.Export.csvHeadColumns', $this->fieldLabels)
            )
        );

        $csv = new Csv();
        $csv->setHeadColumns($csvHeadColumns);

        foreach ($collection as $item) {
            $rowData = rex_extension::registerPoint(
                new rex_extension_point('yform/usability.Export.rowData', $item->getData())
            );
            $csv->addRow($rowData);
        }

        return $csv;
    }
}
