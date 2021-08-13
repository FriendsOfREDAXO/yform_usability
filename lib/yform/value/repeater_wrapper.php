<?php

/**
 * yform
 * @author Kreatif GmbH
 * @author <a href="http://www.kreatif.it">www.kreatif.it</a>
 */
class rex_yform_value_repeater_wrapper extends rex_yform_value_abstract
{

    function enterObject()
    {
        $index    = $this->getElement('repeaterIndex');
        $values   = $this->getElement('values')[$index];
        $settings = json_decode($values['__settings'], true);
        unset($values['__settings']);

        if (empty($settings)) {
            $settings['status'] = 1;
        }

        $this->setElement('repeaterValues', $values);
        $this->setElement('repeaterSettings', $settings);
        $this->setElement('ajaxUrl', rex_url::currentBackendPage($_GET));
        $this->params['form_output'][$this->getId()] = $this->parse('value.repeater_wrapper.tpl.php');
    }

    function getDefinitions($values = [])
    {
        return [
            'type'            => 'value',
            'name'            => 'repeater_wrapper',
            'description'     => rex_i18n::msg('yform_usability.repeater_wrapper_description'),
            'values'          => [
                'name'    => ['type' => 'name', 'label' => rex_i18n::msg('yform_values_defaults_name')],
                'partial' => [
                    'type'    => 'choice',
                    'label'   => rex_i18n::msg('yform_usability.partial'),
                    'choices' => json_encode(
                        [
                            rex_i18n::msg('yform_usability.repeater_start')     => 'start',
                            rex_i18n::msg('yform_usability.repeater_separator') => 'separator',
                            rex_i18n::msg('yform_usability.repeater_end')       => 'end',
                        ]
                    ),
                ],
            ],
            'dbtype'          => 'none',
            'is_hiddeninlist' => true,
            'is_searchable'   => false,
            'famous'          => true,
        ];
    }

    public function postFormAction()
    {
    }

}
