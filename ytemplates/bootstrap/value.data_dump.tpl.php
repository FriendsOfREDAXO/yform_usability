<?php

/**
 * This file is part of the Kreatif\Project package.
 *
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 25.09.19
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$data = $this->getValue();
$data = @unserialize($data);

if ($data === false) {
    $decoded_json = (array)json_decode($this->getValue(), true);

    if (json_last_error() == JSON_ERROR_NONE) {
        $data = $decoded_json;
    } else {
        $data = $this->getValue();
    }
}

?>
<label class="control-label"><?= $this->getLabel() ?></label>
<?php dump($data) ?>
<textarea name="<?= $this->getFieldName() ?>" rows="30" style="display:none;"><?= $this->getValue() ?></textarea>
