<?php

$attributes = [
    'type'  => 'hidden',
    'name'  => $this->getFieldName(),
    'id'    => $this->getFieldId(),
    'value' => $this->getValue(),
];
$attributes = $this->getAttributeElements($attributes);

?>
<div class="hidden-text-wrapper" id="<?= $this->getHTMLId() ?>">
    <?= $this->getElement('prepend') ?>
    <input <?= implode(' ', $attributes) ?> />
    <?= $this->getElement('append') ?>
</div>

