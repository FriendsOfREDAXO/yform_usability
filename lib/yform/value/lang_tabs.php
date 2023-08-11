<?php

/**
 * yform
 * @author Kreatif GmbH
 * @author <a href="http://www.kreatif.it">www.kreatif.it</a>
 */
class rex_yform_value_lang_tabs extends rex_yform_value_abstract
{

    function enterObject()
    {
        if (rex::isBackend()) {
            $this->params['form_output'][$this->getId()] = $this->parse('value.lang_tabs.tpl.php');
        }
    }

    function getDescription(): string
    {
        return htmlspecialchars('tab_start');
    }


   public function getDefinitions($values = []) :array

    {
        return [
            'type'            => 'value',
            'name'            => 'lang_tabs',
            'description'     => rex_i18n::msg('yform_usability.lang_tabs_description'),
            'values'          => [
                'name'    => ['type' => 'name', 'label' => rex_i18n::msg('yform_values_defaults_name')],
                'partial' => [
                    'type'    => 'choice',
                    'label'   => rex_i18n::msg('yform_usability.partial'),
                    'choices' => json_encode([
                        rex_i18n::msg('yform_usability.tab_start') => 'start',
                        rex_i18n::msg('yform_usability.tab_break') => 'break',
                        rex_i18n::msg('yform_usability.tab_end')   => 'end',
                    ]),
                ],
            ],
            'dbtype'          => 'none',
            'is_hiddeninlist' => true,
            'is_searchable'   => false,
            'famous'          => true,
        ];
    }

}
