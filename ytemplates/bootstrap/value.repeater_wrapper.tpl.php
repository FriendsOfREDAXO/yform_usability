<?php

/**
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 10.08.21
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

?>
<?php if ($this->getElement('partial') == 'start'): ?>
    <div class="yform-repeater" data-repeater-container="<?= $this->getName() ?>">
        <div data-repeater-wrapper data-url="<?= $this->getElement('addUrl') ?>">
            <div class="repeater-item">
<?php endif; ?>

    <?php if ($this->getElement('partial') == 'separator'): ?>
            <button class="btn btn-primary" onclick="return YformUsability.addYformRepeatedBlock(this)">add</button>
            </div>
            <div class="repeater-item">
    <?php endif; ?>

<?php if ($this->getElement('partial') == 'end'): ?>
            <button class="btn btn-primary" onclick="return YformUsability.addYformRepeatedBlock(this)">add</button>
            </div>
        </div>
    </div>
<?php endif; ?>