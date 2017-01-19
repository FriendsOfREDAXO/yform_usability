<?php

/**
 * This file is part of the yform/usability package.
 *
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 18.01.17
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace yform\usability;



\rex_extension::register('FE_OUTPUT', function ($params)
{
    // api endpoint
    $api_result = \rex_api_yform_usability_api::factory();
    if ($api_result && $api_result->hasMessage())
    {
        header('Content-Type: application/json');
        echo $api_result->getResult()->toJSON();
        exit;
    }
    return $params->getSubject();
}, \rex_extension::EARLY);



if (\rex::isBackend() && \rex::getUser())
{
    if ($this->getProperty('compile'))
    {
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
    \rex_view::setJsProperty('frontend_url', \rex_url::frontendController());
    \rex_view::addCssFile($this->getAssetsUrl('styles.css?mtime=' . filemtime($this->getAssetsPath('styles.css'))));
    \rex_view::addJsFile($this->getAssetsUrl('vendor/Sortable.min.js?mtime=' . filemtime($this->getAssetsPath('script.js'))));
    \rex_view::addJsFile($this->getAssetsUrl('script.js?mtime=' . filemtime($this->getAssetsPath('script.js'))));

    \rex_extension::register('YFORM_DATA_LIST', ['\yform\usability\Extensions', 'yform_data_list']);
}