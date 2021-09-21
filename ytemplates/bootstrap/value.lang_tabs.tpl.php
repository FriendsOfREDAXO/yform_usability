<?php

/**
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 31.03.21
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$tabIds = rex::getProperty('yform_usability.lang_tab_ids', []);

?>
<?php if ($this->getElement('partial') == 'start'): ?>
<div class="nav rex-page-nav yform-lang-tabs">
    <ul class="nav nav-tabs">
        <?php
        $langs   = rex_clang::getAll();
        $clangId = rex_clang::getCurrentId();
        ?>
        <?php foreach ($langs as $lang): ?>
            <?php
            $tabId    = $this->getFieldId() .'-'. $lang->getId();
            $tabIds[] = $tabId;
            ?>
            <li class="<?= $lang->getId() == $clangId ? 'active' : '' ?>">
                <a href="#form-tab-content-col-<?= $tabId ?>" data-toggle="tab">
                    <?= $lang->getName() ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content lang-tab-content">
        <div class="tab-pane fade in active" id="form-tab-content-col-<?= array_shift($tabIds) ?>">
        <?php endif; ?>


        <?php if ($this->getElement('partial') == 'break'): ?>
        </div>
        <div class="tab-pane fade" id="form-tab-content-col-<?= array_shift($tabIds) ?>">
        <?php endif; ?>


        <?php if ($this->getElement('partial') == 'end'): ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php

rex::setProperty('yform_usability.lang_tab_ids', $tabIds);