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
use yform\usability\lib\Sql;


class Model extends \rex_yform_manager_dataset
{

    public static function get($id, $isOnline = false): ?\rex_yform_manager_dataset
    {
        return (int)$id > 0 ? parent::get((int)$id) : null;
    }

    public static function getDbTable(): string
    {
        return str_replace('{PREFIX}', \rex::getTablePrefix(), static::TABLE);
    }

    public static function getSql(): Sql
    {
        $sql = Sql::factory();
        $sql->setTable(static::getDbTable());
        return $sql;
    }

    public function inserUpdate(): Sql
    {
        $sql = self::getSql();

        foreach ($this->getData() as $key => $value) {
            $sql->setValue($key, $value);
        }

        try {
            if ($_id = $this->getId()) {
                $sql->setWhere('id = :id', ['id' => $_id]);
                $sql->update();
            } else {
                $sql->insert();
                $this->setId($sql->getLastId());
            }
        } catch (\rex_sql_exception $ex) {
            // error is passed by getError method
        }
        return $sql;
    }

    public function getRawValue($key)
    {
        return parent::getValue($key);
    }

    public function valueIsset($key, $langId = false)
    {
        $value = $this->getValue($key, $langId);
        return is_object($value) || strlen($value) > 0;
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

    public function getObjectValue($key, $langId = false)
    {
        return unserialize($this->getValue($key, $langId));
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

    public function getName(int $langId = null)
    {
        $langId = $langId ?? \rex_clang::getCurrentId();
        $name   = $this->getValue('name', $langId);


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

    public function getDescription(int $langId = null)
    {
        $langId      = $langId ?? \rex_clang::getCurrentId();
        $description = $this->getValue('description', $langId);


        if ($description === null) {
            $description = $this->getValue('text', $langId);

            if ($description === null) {
                $description = $this->getValue('description');

                if ($description === null) {
                    $description = $this->getValue('text');
                }
            }
        }
        return $description;
    }

    public function getCreateDate()
    {
        return strtotime($this->getValue('createdate'));
    }

    public function getUpdateDate()
    {
        return strtotime($this->getValue('updatedate'));
    }

    public static function hasUrlProfile($langId = null): bool
    {
        $found    = false;
        $caller   = get_called_class();
        $langId   = $langId ?? \rex_clang::getCurrentId();
        $profiles = Profile::getByTableName($caller::getDbTable());

        foreach ($profiles as $profile) {
            if (null == $profile->getArticleClangId() || $profile->getArticleClangId() == $langId) {
                $found = true;
                break;
            }
        }
        return $found;
    }

    public static function addQueryDefaultFilters(\rex_yform_manager_query $query, $alias = 'm'): void
    {
        $query->where("{$alias}.status", 1);
    }

    public function save()
    {
        if (\rex::isFrontend()) {
            unset($_POST['FORM']);
        }
        return parent::save();
    }
}