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

$func  = rex_get('func', 'string');
$id    = rex_get('id', 'int');
$table = rex_get('table_name', 'string');
$Class = \rex_yform_manager_dataset::getModelClass($table);

switch ($func) {
    case 'duplicate':
        $data = $Class::get($id)->getData();
        unset($data['id']);

        $Object = $Class::create();

        foreach ($data as $key => $value) {
            $Object->setValue($key, $value);
        }
        if (!$Object->save()) {
            echo \rex_view::error(implode('<br/>', $Object->getMessages()));
            exit;
        }
        break;
}

header('Location: '. \rex_url::backendPage('yform/manager/data_edit', ['table_name' => $table], false));
exit;