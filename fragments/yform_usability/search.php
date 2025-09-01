<?php

/**
 * This file is part of the Kreatif\Project package.
 *
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 15.04.19
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$manager    = $this->getVar('manager');
$term       = rex_request('yfu-term', 'string');
$openerData = rex_get('rex_yform_manager_opener', 'array');
$fields     = $manager->table->getFields();
$selFields  = array_filter(explode(',', rex_request('yfu-searchfield', 'string')));
$getParams  = [];
$optionVals = ['id'];
$options    = [
    [
        'value' => 'id',
        'label' => 'ID',
    ],
];


foreach ($fields as $field) {
    if ($field->getTypeName() && $field->getType() == 'value' && $field->isSearchable()) {
        $optionVals[] = $field->getName();
        $options[]    = [
            'value' => $field->getName(),
            'label' => $field->getLabel(),
        ];
    }
}

if ($openerData) {
    $getParams['rex_yform_manager_opener'] = $openerData;
}

?>
<form action="<?= rex_url::backendPage('yform/manager/data_edit', array_merge($getParams, ['table_name' => $manager->table->getTablename()])) ?>" method="get" onsubmit="return false;" id="yform_usability-search" class="<?= $term != '' ? 'filtered' : '' ?>">
    <div class="form-group">
        <div class="row">
            <div class="col-sm-2">
                <label class="control-label">Suche</label>
            </div>
            <div class="col-sm-4">
                <select name="yfu-searchfield" class="form-control selectpicker" 
                        data-style="btn-default" data-size="8" data-container="body"
                        data-live-search="<?= count($options) > 10 ? 'true' : 'false' ?>"
                        onchange="YformUsability.doYformSearch(this, event)">
                    <?php if (count($options) > 1): ?>
                        <option value="<?= implode(',', $optionVals) ?>">- <?= rex_i18n::msg('yform_usability.search_in_all') ?> -</option>
                    <?php endif; ?>

                    <?php foreach ($options as $option): ?>
                        <option value="<?= $option['value'] ?>" <?= count($selFields) == 1 && in_array($option['value'], $selFields) ? 'selected' : '' ?>><?= $option['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6">
                <div class="input-wrapper">
                    <input type="text" name="yfu-term" class="form-control" value="<?= rex_request('yfu-term', 'string') ?>" onfocus="this.selectionStart = this.selectionEnd = this.value.length;">
                    <i class="fa fa-times-circle filter-reset" onclick="YformUsability.resetYformSearch(this)"></i>
                </div>
            </div>
        </div>
        <input type="hidden" name="yfu-action" value="search">
    </div>
</form>