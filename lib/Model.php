<?php

/**
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 28.04.21
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability;


use Url\Profile;


class Model extends \rex_yform_manager_dataset
{

    public static function get($id, $isOnline = false): ?\rex_yform_manager_dataset
    {
        return (int)$id > 0 ? parent::get((int)$id) : null;
    }

    public function getRawValue($key)
    {
        return parent::getValue($key);
    }

    public function valueIsset($key, $langId = false)
    {
        $value = $this->getValue($key, $langId);
        return strlen($value) > 0;
    }

    public function getValue($key, $langId = false, $default = '')
    {
        if ($langId) {
            $key .= '_' . ($langId === true ? \rex_clang::getCurrentId() : $langId);
        }
        $value = parent::getValue($key);
        $value = !is_string($value) || strlen($value) ? $value : ($value === null ? null : $default);
        return $value;
    }

    public function getArrayValue($key, $langId = false, $default = [], $separator = ','): array
    {
        $result = $default;

        if ($langId) {
            $key .= '_' . ($langId === true ? \rex_clang::getCurrentId() : $langId);
        }
        $value = $this->getRawValue($key);

        if (strlen($value)) {
            $decoded_json = (array)json_decode($value, true);

            if (json_last_error() == JSON_ERROR_NONE) {
                $result = $decoded_json;
            } else {
                $result = explode($separator, $value);
            }
        }
        return array_filter((array)$result);
    }

    public function getName($langId = true)
    {
        $name = $this->getValue('name', $langId);


        if ($name === null) {
            $name = $this->getValue('title', $langId);

            if ($name === null) {
                $name = $this->getValue('name');

                if ($name === null) {
                    $name = $this->getValue('title');
                }
            }
        }
        return $name;
    }

    public static function hasUrlProfile($langId = null): bool
    {
        $found    = false;
        $caller   = get_called_class();
        $langId   = $langId ?? \rex_clang::getCurrentId();
        $profiles = Profile::getByTableName($caller::TABLE);

        foreach ($profiles as $profile) {
            if (null == $profile->getArticleClangId() || $profile->getArticleClangId() == $langId) {
                $found = true;
                break;
            }
        }
        return $found;
    }


    public function save()
    {
        if (\rex::isFrontend()) {
            unset($_POST['FORM']);
        }
        return parent::save();
    }
}