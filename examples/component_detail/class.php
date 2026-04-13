<?php
/**
 * Пример компонента детальной страницы
 *
 * Вызов:
 *   $APPLICATION->IncludeComponent('easy:news.detail', '', [
 *       'IBLOCK_CODE' => 'news',
 *       'ELEMENT_CODE' => $elementCode,   // или ELEMENT_ID
 *   ]);
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use BitrixCdd\Infrastructure\DetailComponent;

class NewsDetailComponent extends DetailComponent
{
    protected string $iblockCode = 'news';

    public function executeComponent()
    {
        Loader::requireModule('iblock');

        if ($this->startResultCache($this->arParams['CACHE_TIME'])) {
            $this->arResult['ITEM'] = $this->getItem();

            if (!$this->arResult['ITEM']) {
                $this->abortResultCache();
                $this->set404();
                return;
            }

            $this->registerCacheTags();
            $this->render();
        }
    }
}
