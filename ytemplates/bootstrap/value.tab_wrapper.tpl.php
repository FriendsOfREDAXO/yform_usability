<?php

/**
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 10.08.21
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$tabLabels = rex::getProperty('yform_usability/value_tab_wrapper.labels', []);
$tabIds    = rex::getProperty('yform_usability/value_tab_wrapper.tabIds', []);

?>
<?php if ($this->getElement('partial') == 'head'): ?>
    <div class="nav rex-page-nav yform-lang-tabs">
    <ul class="nav nav-tabs">
        <?php foreach ($tabLabels as $tabId => $item): ?>
            <li class="<?= $item['isActive'] ? 'active' : '' ?>">
                <a href="#form-tab-content-col-<?= $tabId ?>" data-toggle="tab">
                    <?= $item['label'] ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
    <div class="tab-pane fade in active" id="form-tab-content-col-<?= array_shift($tabIds) ?>">
<?php endif; ?>


<?php if ($this->getElement('partial') == 'separator'): ?>
    </div>
    <div class="tab-pane fade" id="form-tab-content-col-<?= array_shift($tabIds) ?>">
<?php endif; ?>


<?php if ($this->getElement('partial') == 'footer'): ?>
    </div>
    </div>
    </div>
<?php endif; ?>

<?php

rex::setProperty('yform_usability/value_tab_wrapper.tabIds', $tabIds);