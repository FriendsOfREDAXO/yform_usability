<?php

/**
 * This file is part of the Kreatif\Project package.
 *
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 06.08.21
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability\Model;


use yform\usability\Model;


class ArticleSlice extends Model
{

    public static function query($table = null): \rex_yform_manager_query
    {
        $query = parent::query(\rex::getTable('article_slice'));
        $query->orderBy('priority');
        return $query;
    }
}