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

use rex;
use rex_addon;
use rex_be_controller;
use rex_csrf_token;
use rex_extension;
use rex_file;
use rex_scss_compiler;
use rex_url;
use rex_view;
use rex_yform;

$addon = rex_addon::get('yform_usability');

// init all extension points
Extensions::init();


if (rex::isBackend() && rex::getUser()) {
    if ($addon->getProperty('compile')) {
        $compiler   = new rex_scss_compiler();
        $scss_files = [$addon->getPath('assets/scss/styles.scss')];
        $compiler->setRootDir($addon->getPath('assets/'));
        $compiler->setScssFile($scss_files);
        $compiler->setCssFile($addon->getPath('assets/styles.css'));
        $compiler->compile();
        rex_file::copy($addon->getPath('assets/styles.css'), $addon->getAssetsPath('styles.css'));
        //\rex_file::delete($addon->getPath('assets/styles.css'));

        rex_file::copy($addon->getPath('assets/vendor/Sortable.min.js'), $addon->getAssetsPath('vendor/Sortable.min.js'));
        rex_file::copy($addon->getPath('assets/script.js'), $addon->getAssetsPath('script.js'));
    }

    rex_view::setJsProperty('ajax_url', rex_url::frontendController(
        rex_csrf_token::factory('rex_api_yform_usability_api')->getUrlParams()));
    rex_view::addCssFile($addon->getAssetsUrl('styles.css?mtime=' . filemtime($addon->getAssetsPath('styles.css'))));

    switch (rex_be_controller::getCurrentPagePart(1)) {
        case 'content':
            break;
        default:
            rex_view::addJsFile($addon->getAssetsUrl('vendor/Sortable.min.js?mtime=' . filemtime($addon->getAssetsPath('script.js'))));
            rex_view::addJsFile($addon->getAssetsUrl('script.js?mtime=' . filemtime($addon->getAssetsPath('script.js'))));
            break;
    }

    rex_yform::addTemplatePath($addon->getPath('ytemplates'));
    rex_extension::register('PACKAGES_INCLUDED', [Usability::class, 'init']);
    rex_extension::register('YFORM_MANAGER_DATA_PAGE', [Extensions::class, 'ext_yformManagerDataPage']);
    rex_extension::register('YFORM_DATA_LIST', [Extensions::class, 'ext_yformDataList']);
    rex_extension::register('YFORM_MANAGER_REX_INFO', [Extensions::class, 'ext_yformManagerRexInfo']);
    rex_extension::register('YFORM_DATA_LIST_QUERY', [Extensions::class, 'ext_yformDataListSql']);
    rex_extension::register('REX_LIST_GET', [Extensions::class, 'ext_rexListGet']);
    rex_extension::register('YFORM_DATA_LIST_ACTION_BUTTONS', [Extensions::class, 'yform_data_list_action_buttons']);

}

// includes ytemplates in cli environment(for cronjob tasks)
if ('cli' === PHP_SAPI && !rex::isSetup()) {
    rex_yform::addTemplatePath($addon->getPath('ytemplates'));
}
