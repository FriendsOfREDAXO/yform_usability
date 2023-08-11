<?php

/**
 * This file is part of the Kreatif\Project package.
 *
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 25.09.19
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class rex_yform_value_data_dump extends rex_yform_value_abstract
{
    public function enterObject()
    {
        if (rex::isBackend()) {
            if (array_key_exists($this->getName(), (array)$this->params['value_pool']['sql_overwrite'])) {
                $this->setValue($this->params['value_pool']['sql_overwrite'][$this->getName()]);
            }
            if ($this->getValue() !== null && !is_string($this->getValue())) {
                $this->setValue(serialize($this->getValue()));
            }
            if ($this->needsOutput()) {
                $this->params['form_output'][$this->getId()] = $this->parse('value.data_dump.tpl.php');
            }
            $this->params['value_pool']['email'][$this->getName()] = $this->getValue();
            $this->params['value_pool']['sql'][$this->getName()] = $this->getValue();
        }
    }

    public function getDefinitions($values = []): array
    {
        return [
            'type' => 'value',
            'name' => 'data_dump',
            'values' => [
                'name' => ['type' => 'name', 'label' => rex_i18n::msg("yform_values_defaults_name")],
                'label' => ['type' => 'text', 'label' => rex_i18n::msg("yform_values_defaults_label")],
                'notice' => ['type' => 'text', 'label' => rex_i18n::msg('yform_values_defaults_notice')],
            ],
            'description' => "Ausgabe für verschiedene Daten-Typen (Array, Object, XML, ...)",
            'is_searchable' => false,
            'dbtype' => 'text',
            'db_null' => true,
            'default' => null,
        ];
    }
}
