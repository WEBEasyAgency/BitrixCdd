<?php
/**
 * Пример компонента-списка с пагинацией
 *
 * Вызов:
 *   $APPLICATION->IncludeComponent('easy:news.list', '', [
 *       'IBLOCK_CODE' => 'news',   // или задать $iblockCode в классе
 *       'PAGE_SIZE' => 12,          // по умолчанию 10
 *   ]);
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use BitrixCdd\Infrastructure\ListComponent;

class NewsListComponent extends ListComponent
{
    protected string $iblockCode = 'news';
    protected int $pageSize = 12;

    public function executeComponent()
    {
        Loader::requireModule('iblock');

        if ($this->startResultCache($this->arParams['CACHE_TIME'])) {
            $this->arResult['ITEMS'] = $this->getPagedItems(
                order: ['SORT' => 'ASC']
            );

            if (empty($this->arResult['ITEMS'])) {
                $this->abortResultCache();
                return;
            }

            $this->registerCacheTags();
            $this->render();
        }
    }
}
