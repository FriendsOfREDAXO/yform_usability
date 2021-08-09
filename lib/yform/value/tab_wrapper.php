<?php

/**
 * yform
 * @author Kreatif GmbH
 * @author <a href="http://www.kreatif.it">www.kreatif.it</a>
 */
class rex_yform_value_tab_wrapper extends rex_yform_value_abstract
{

    function enterObject()
    {
        if ($this->getElement('partial') == 'head') {
            $options = explode(',', $this->getElement('options'));

            $labels = [];
            foreach ($options as $index => $label) {
                $labels[sha1($index)] = [
                    'isActive' => $index == 0,
                    'label'    => $label,
                ];
            }
            rex::setProperty('yform_usability/value_tab_wrapper.labels', $labels);
            rex::setProperty('yform_usability/value_tab_wrapper.tabIds', array_keys($labels));
        }

        $this->params['form_output'][$this->getId()] = $this->parse('value.tab_wrapper.tpl.php');
    }

    function getDescription()
    {
        return htmlspecialchars('tab_start');
    }

    function getDefinitions($values = [])
    {
        return [
            'type'            => 'value',
            'name'            => 'tab_wrapper',
            'description'     => rex_i18n::msg('yform_usability.tab_wrapper_description'),
            'values'          => [
                'name'    => ['type' => 'name', 'label' => rex_i18n::msg('yform_values_defaults_name')],
                'partial' => [
                    'type'    => 'choice',
                    'label'   => rex_i18n::msg('yform_usability.partial'),
                    'choices' => json_encode(
                        [
                            rex_i18n::msg('yform_usability.tab_start') => 'head',
                            rex_i18n::msg('yform_usability.tab_break') => 'separator',
                            rex_i18n::msg('yform_usability.tab_end')   => 'footer',
                        ]
                    ),
                ],
                'options' => ['type' => 'name', 'label' => rex_i18n::msg('yform_usability.tab_wrapper_options')],
            ],
            'dbtype'          => 'none',
            'is_hiddeninlist' => true,
            'is_searchable'   => false,
            'famous'          => true,
        ];
    }

}
