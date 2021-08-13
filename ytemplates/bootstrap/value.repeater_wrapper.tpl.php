<?php

/**
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 10.08.21
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/** @var rex_yform_value_abstract $this */

$settings = $this->getElement('repeaterSettings');

?>
<?php if ($this->getElement('partial') == 'start'): ?>
    <div class="yform-repeater">
        <div data-repeater-wrapper data-repeater-id="<?= $this->getElement('valueId') ?>" data-ajax-url="<?= $this->getElement('ajaxUrl') ?>">
            <button class="btn btn-primary add-block-btn" onclick="return YformUsability.addYformRepeatedBlock(this)">
                + <?= rex_i18n::msg('yform_usability.add_repeater_block') ?>
            </button>
            <div class="repeater-item <?= $settings['status'] == 1 ? 'online' : 'offline' ?>" data-repeater-item>
                <div class="toolbar">
                    <div class="btn-group btn-group-xs">
                        <a href="javascript:;" class="btn btn-move" title="Deaktivieren" onclick="return YformUsability.toggleYformRepeatedBlock(this, <?= (int)!((bool)$settings['status']) ?>)">
                            <i class="rex-icon <?= $settings['status'] == 1 ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                        </a>
                        <a href="javascript:;" class="btn btn-move" title="Löschen" onclick="return YformUsability.rmYformRepeatedBlock(this)">
                            <i class="rex-icon fa-trash"></i>
                        </a>
                    </div>
                    <div class="btn-group btn-group-xs">
                        <a href="#" class="btn btn-move" title="Nach oben verschieben">
                            <i class="rex-icon rex-icon-up"></i>
                        </a>
                        <a href="#" class="btn btn-move" title="Nach unten verschieben">
                            <i class="rex-icon rex-icon-down"></i>
                        </a>
                    </div>
                </div>
                <div class="form-fields">
<?php endif; ?>

    <?php if ($this->getElement('partial') == 'separator'): ?>
                </div>
                <button class="btn btn-primary add-block-btn" onclick="return YformUsability.addYformRepeatedBlock(this)">
                    + <?= rex_i18n::msg('yform_usability.add_repeater_block') ?>
                </button>
            </div>
            <div class="repeater-item  <?= $settings['status'] == 1 ? 'online' : 'offline' ?>" data-repeater-item>
                <div class="toolbar">
                    <div class="btn-group btn-group-xs">
                        <a href="javascript:;" class="btn btn-move" title="Deaktivieren" onclick="return YformUsability.toggleYformRepeatedBlock(this, <?= (int)!((bool)$settings['status']) ?>)">
                            <i class="rex-icon <?= $settings['status'] == 1 ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                        </a>
                        <a href="javascript:;" class="btn btn-move" title="Löschen" onclick="return YformUsability.rmYformRepeatedBlock(this)">
                            <i class="rex-icon fa-trash"></i>
                        </a>
                    </div>
                    <div class="btn-group btn-group-xs">
                        <a href="#" class="btn btn-move" title="Nach oben verschieben">
                            <i class="rex-icon rex-icon-up"></i>
                        </a>
                        <a href="#" class="btn btn-move" title="Nach unten verschieben">
                            <i class="rex-icon rex-icon-down"></i>
                        </a>
                    </div>
                </div>
                <div class="form-fields">
    <?php endif; ?>

<?php if ($this->getElement('partial') == 'end'): ?>
                </div>
                <button class="btn btn-primary add-block-btn" onclick="return YformUsability.addYformRepeatedBlock(this)">
                    + <?= rex_i18n::msg('yform_usability.add_repeater_block') ?>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>