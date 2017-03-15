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


if (rex_post('btn_save', 'string') == 'save') {

    // set config
    $config = array_merge($this->getConfig(null, []), rex_post('settings', 'array'));

    if (!count($error)) {
        $this->setConfig($config);
        $success = \rex_i18n::msg('info_updated');
    }
}


$config = $this->getConfig();

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
            <select name="settings[has_status]" class="form-control">
                <option value="1" ' . ($config['has_status'] ? 'selected="selected"' : '') . '>' . \rex_i18n::msg('yes') . '</option>
                <option value="0" ' . (!$config['has_status'] ? 'selected="selected"' : '') . '>' . \rex_i18n::msg('no') . '</option>
            </select>
        ',
    ],
    [
        'label' => '<label>' . $this->i18n('label.sorting') . '</label>',
        'field' => '
            <select name="settings[has_sorting]" class="form-control">
                <option value="1" ' . ($config['has_sorting'] ? 'selected="selected"' : '') . '>' . \rex_i18n::msg('yes') . '</option>
                <option value="0" ' . (!$config['has_sorting'] ? 'selected="selected"' : '') . '>' . \rex_i18n::msg('no') . '</option>
            </select>
        ',
    ],
    [
        'label' => '<label>' . $this->i18n('label.duplication') . '</label>',
        'field' => '
            <select name="settings[has_duplication]" class="form-control">
                <option value="1" ' . ($config['has_duplication'] ? 'selected="selected"' : '') . '>' . \rex_i18n::msg('yes') . '</option>
                <option value="0" ' . (!$config['has_duplication'] ? 'selected="selected"' : '') . '>' . \rex_i18n::msg('no') . '</option>
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