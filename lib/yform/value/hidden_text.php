<?php

/**
 * This file is part of the Kreatif\Project package.
 *
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 13.08.21
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class rex_yform_value_hidden_text extends rex_yform_value_text
{

    public function enterObject()
    {
        $this->setLabel('');
        parent::enterObject();

        $this->params['form_output'][$this->getId()] = $this->parse('value.hidden_text.tpl.php');
    }

    public function getDefinitions($values = [])
    {
        $params           = parent::getDefinitions($values);
        $params['name']   = 'hidden_text';
        $params['famous'] = false;
        return $params;
    }
}