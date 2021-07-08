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


// init all extension points
Extensions::init();

\rex_extension::register('PAGE_CHECKED', function ($params) {
    if (\rex_request('rex-api-call', 'string') == 'yform_usability_api') {
        // api endpoint
        $api_result = \rex_api_yform_usability_api::factory();

        \rex_api_function::handleCall();

        if ($api_result && $api_result->getResult()) {
            \rex_response::cleanOutputBuffers();
            \rex_response::sendContent($api_result->getResult()->toJSON(), 'application/json');
            exit;
        }
    }
}, \rex_extension::EARLY);


if (\rex::isBackend() && \rex::getUser()) {
    if (1 == rex_get('compile', 'int') || !file_exists($this->getAssetsPath('styles.css'))) {
        $compiler   = new \rex_scss_compiler();
        $scss_files = [$this->getPath('assets/scss/styles.scss')];
        $compiler->setRootDir($this->getPath('assets/'));
        $compiler->setScssFile($scss_files);
        $compiler->setCssFile($this->getPath('assets/styles.css'));
        $compiler->compile();
        \rex_file::copy($this->getPath('assets/styles.css'), $this->getAssetsPath('styles.css'));
        \rex_file::delete($this->getPath('assets/styles.css'));

        \rex_file::copy($this->getPath('assets/vendor/Sortable.min.js'), $this->getAssetsPath('vendor/Sortable.min.js'));
        \rex_file::copy($this->getPath('assets/script.js'), $this->getAssetsPath('script.js'));
    }

    \rex_view::setJsProperty('ajax_url', \rex_url::backendPage('yform/manager/usability', \rex_csrf_token::factory('rex_api_yform_usability_api')->getUrlParams()));
    \rex_view::addCssFile($this->getAssetsUrl('styles.css?mtime=' . filemtime($this->getAssetsPath('styles.css'))));
    \rex_view::addJsFile($this->getAssetsUrl('vendor/Sortable.min.js?mtime=' . filemtime($this->getAssetsPath('script.js'))));
    \rex_view::addJsFile($this->getAssetsUrl('script.js?mtime=' . filemtime($this->getAssetsPath('script.js'))));
    \rex_yform::addTemplatePath($this->getPath('ytemplates'));

    \rex_extension::register('PACKAGES_INCLUDED', [Usability::class, 'init']);
    \rex_extension::register('YFORM_MANAGER_DATA_PAGE', [Extensions::class, 'ext_yformManagerDataPage']);
    \rex_extension::register('YFORM_DATA_LIST', [Extensions::class, 'ext_yformDataList']);
    \rex_extension::register('YFORM_MANAGER_REX_INFO', [Extensions::class, 'ext_yformManagerRexInfo']);
    \rex_extension::register('YFORM_DATA_LIST_SQL', [Extensions::class, 'ext_yformDataListSql']);
    \rex_extension::register('REX_LIST_GET', [Extensions::class, 'ext_rexListGet']);
}