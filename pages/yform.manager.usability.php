<?php
/**
 * This file is part of the yform/usability package.
 *
 * @author Friends Of REDAXO
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability;

use rex_addon;
use rex_config_form;
use rex_fragment;
use rex_i18n;
use rex_url;
use rex_view;
use rex_yform_manager_table;

echo rex_view::title(rex_i18n::msg('yform'));

$addon  = rex_addon::get('yform_usability');
$config = Usability::getConfig();

$content = '
    <a style="float:right; font-size: 2em; line-height: 0;" href="' . rex_url::backendPage('packages', ['subpage' => 'help', 'package' => 'yform_usability']) . '" title="' . $this->i18n('usability.help_open_readme') . '">
        <i class="fa fa-question-circle"></i>
    </a>
    <p>' . $this->i18n('yform_usability.help_prio') . '</p>
    <p style="margin-bottom:0">' . $this->i18n('yform_usability.help_status') . '</p>
';

$fragment = new rex_fragment();
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


$tables = [];
foreach (rex_yform_manager_table::getAll() as $table) {
    $tables[$table->getTableName()] = $table->getName();
}
asort($tables);


$form = rex_config_form::factory('yform_usability');

$form->addFieldset($addon->i18n('yform_usability.table_config'));
{
    $form->addRawField('<div data-toggle-wrapper>');
    $field = $form->addCheckboxField('status_tables_all', empty($config) ? 1 : null);
    $field->setLabel($addon->i18n('yform_label.online_status'));
    $field->addOptions([1 => $addon->i18n('yform_usability.all_tables')]);

    $field  = $form->addSelectField('status_tables');
    $select = $field->getSelect();
    $select->setMultiple();
    $select->addOptions($tables);
    $form->addRawField('</div>');
}
{
    $form->addRawField('<div data-toggle-wrapper>');
    $field = $form->addCheckboxField('sorting_tables_all', empty($config) ? 1 : null);
    $field->setLabel($addon->i18n('yform_label.sorting'));
    $field->addOptions([1 => $addon->i18n('yform_usability.all_tables')]);

    $field  = $form->addSelectField('sorting_tables');
    $select = $field->getSelect();
    $select->setMultiple();
    $select->addOptions($tables);
    $form->addRawField('</div>');
}
{
    $form->addRawField('<div data-toggle-wrapper>');
    $field = $form->addCheckboxField('duplicate_tables_all', empty($config) ? 1 : null);
    $field->setLabel($addon->i18n('yform_label.duplication'));
    $field->addOptions([1 => $addon->i18n('yform_usability.all_tables')]);

    $field  = $form->addSelectField('duplicate_tables');
    $select = $field->getSelect();
    $select->setMultiple();
    $select->addOptions($tables);
    $form->addRawField('</div>');
}
{
    $form->addRawField('<div data-toggle-wrapper>');
    $field = $form->addCheckboxField('use_inline_search', empty($config) ? 1 : null);
    $field->setLabel($addon->i18n('yform_usability.search'));
    $field->addOptions([1 => $addon->i18n('yform_usability.all_tables')]);

    $field  = $form->addSelectField('search_tables');
    $select = $field->getSelect();
    $select->setMultiple();
    $select->addOptions($tables);
    $form->addRawField('</div>');
}

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', rex_i18n::msg('settings'));
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');
