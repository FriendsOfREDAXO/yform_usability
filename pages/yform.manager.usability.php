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

echo \rex_view::title(\rex_i18n::msg('yform'));

$info    = '';
$success = '';
$error   = [];
$tables  = [];
$_tables = \rex_yform_manager_table::getAll();

if (rex_post('btn_save', 'string') == 'save') {

    // set config
    $default_config = \rex_addon::get('yform_usability')->getProperty('default_config');
    $config  = rex_post('settings', 'array');

    foreach ($default_config as $key => $value) {
        if (isset($config[$key])) {
            \rex_addon::get('yform_usability')->setConfig($key, $config[$key]);
        }
        else {
            \rex_addon::get('yform_usability')->setConfig($key, $default_config[$key]);
        }
    }
    $success = \rex_i18n::msg('info_updated');
}

$config = \rex_addon::get('yform_usability')->getConfig();

$status_options    = ['<option value="all" ' . (in_array('all', (array) $config['status_tables']) ? 'selected="selected"' : '') . '>- ' . $this->i18n('label.all') . ' -</option>'];
$sorting_options   = ['<option value="all" ' . (in_array('all', (array) $config['sorting_tables']) ? 'selected="selected"' : '') . '>- ' . $this->i18n('label.all') . ' -</option>'];
$duplicate_options = ['<option value="all" ' . (in_array('all', (array) $config['duplicate_tables']) ? 'selected="selected"' : '') . '>- ' . $this->i18n('label.all') . ' -</option>'];

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
            <select name="settings[status_tables][]" class="form-control" multiple="multiple" size="'.count($status_options).'">
                ' . implode('', $status_options) . '
            </select>
        ',
    ],
    [
        'label' => '<label>' . $this->i18n('label.sorting') . '</label>',
        'field' => '
            <select name="settings[sorting_tables][]" class="form-control" multiple="multiple" size="'.count($sorting_options).'">
                ' . implode('', $sorting_options) . '
            </select>
        ',
    ],
    [
        'label' => '<label>' . $this->i18n('label.duplication') . '</label>',
        'field' => '
            <select name="settings[duplicate_tables][]" class="form-control" multiple="multiple" size="'.count($duplicate_options).'">
                ' . implode('', $duplicate_options) . '
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
$fragment->setVar('title', $this->i18n('usability.settings'), false);
$fragment->setVar('body', implode('', $content), false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

?>
<section class="rex-page-section">
    <div class="panel help-block">
        <div class="panel-body">
            <a style="float:right; font-size: 2em; line-height: 0;" href="index.php?page=packages&subpage=help&package=yform_usability" title="<?php echo $this->i18n('usability.help_open_readme'); ?>"><i class="fa fa-question-circle"></i></a>
            <p><?php echo $this->i18n('usability.help_prio'); ?></p>
            <p style="margin-bottom:0"><?php echo $this->i18n('usability.help_status'); ?></p>
        </div>
    </div>
</section>
<form action="" method="post">
    <?= $content ?>
</form>
