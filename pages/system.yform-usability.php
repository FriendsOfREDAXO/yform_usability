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

$info    = '';
$success = '';
$error   = [];
$tables  = [];
$_tables = \rex_yform_manager_table::getAll();

if (rex_post('btn_save', 'string') == 'save') {

    // set config
    $config = array_merge($this->getConfig(null, []), rex_post('settings', 'array'));

    if (!count($error)) {
        $this->setConfig($config);
        $success = \rex_i18n::msg('info_updated');
    }
}


$config = $this->getConfig();

$status_options    = ['<option value="all" '. (in_array('all', (array) $config['status_tables']) ? 'selected="selected"' : '') .'>- '. $this->i18n('label.all') .' -</option>'];
$sorting_options   = ['<option value="all" '. (in_array('all', (array) $config['sorting_tables']) ? 'selected="selected"' : '') .'>- '. $this->i18n('label.all') .' -</option>'];
$duplicate_options = ['<option value="all" '. (in_array('all', (array) $config['duplicate_tables']) ? 'selected="selected"' : '') .'>- '. $this->i18n('label.all') .' -</option>'];

foreach ($_tables as $table) {
    $tables[$table->getTableName()] = $table->getName();

    $status_options[]    = '<option value="' . $table->getTableName() . '" ' . (in_array($table->getTableName(), (array) $config['status_tables']) ? 'selected="selected"' : '') . '>' . $table->getName() . '</option>';
    $sorting_options[]   = '<option value="' . $table->getTableName() . '" ' . (in_array($table->getTableName(), (array) $config['sorting_tables']) ? 'selected="selected"' : '') . '>' . $table->getName() . '</option>';
    $duplicate_options[] = '<option value="' . $table->getTableName() . '" ' . (in_array($table->getTableName(), (array) $config['duplicate_tables']) ? 'selected="selected"' : '') . '>' . $table->getName() . '</option>';
}

// messages
if (count($error)) {
    echo \rex_view::error(implode('<br />', $error));
}
if ($info != '') {
    echo \rex_view::info($info);
}
if ($success != '') {
    echo \rex_view::success($success);
}


// output
$content = [];

$formElements = [
    [
        'label' => '<label>' . $this->i18n('label.online_status') . '</label>',
        'field' => '
            <select name="settings[status_tables][]" class="form-control" multiple="multiple" size="8">
                '. implode('', $status_options) .'
            </select>
        ',
    ],
    [
        'label' => '<label>' . $this->i18n('label.sorting') . '</label>',
        'field' => '
            <select name="settings[sorting_tables][]" class="form-control" multiple="multiple" size="8">
                '. implode('', $sorting_options) .'
            </select>
        ',
    ],
    [
        'label' => '<label>' . $this->i18n('label.duplication') . '</label>',
        'field' => '
            <select name="settings[duplicate_tables][]" class="form-control" multiple="multiple" size="8">
                '. implode('', $duplicate_options) .'
            </select>
        ',
    ],
];

$fragment = new \rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content[] = $fragment->parse('core/form/form.php');

// form - Button
$formElements = [
    [
        'field' => '<button class="btn btn-save rex-form-aligned" type="submit" name="btn_save" value="save">' . \rex_i18n::msg('update') . '</button>',
    ],
];
$fragment     = new \rex_fragment();
$fragment->setVar('flush', true);
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

// section
$fragment = new \rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $this->i18n('settings'), false);
$fragment->setVar('body', implode('', $content), false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

?>
<form action="" method="post">
    <?= $content ?>
</form>