<?php

namespace BitrixCdd\Infrastructure;

/**
 * Компонент-список с пагинацией
 *
 * Добавляет к BaseComponent:
 * - Постраничная навигация
 * - $arResult['NAV'] с данными пагинации
 * - Параметры PAGE_SIZE (по умолчанию 10) и PAGE_NUM
 *
 * class NewsListComponent extends ListComponent {
 *     protected string $iblockCode = 'news';
 *     protected int $pageSize = 12;
 * }
 */
class ListComponent extends BaseComponent
{
    /** Количество элементов на странице. Переопределяется в наследнике или через PAGE_SIZE */
    protected int $pageSize = 10;

    public function onPrepareComponentParams($arParams)
    {
        $arParams = parent::onPrepareComponentParams($arParams);

        if (!empty($arParams['PAGE_SIZE'])) {
            $this->pageSize = (int)$arParams['PAGE_SIZE'];
        }

        return $arParams;
    }

    /**
     * Получить элементы с пагинацией
     * Заполняет $this->arResult['NAV']
     */
    protected function getPagedItems(array $filter = ['ACTIVE' => 'Y'], array $order = ['SORT' => 'ASC']): array
    {
        $page = $this->getCurrentPage();
        $manager = $this->getManager();

        if (!$manager) {
            $this->arResult['NAV'] = $this->buildNav(0, $page);
            return [];
        }

        $totalCount = $manager->getCount($filter);
        $this->arResult['NAV'] = $this->buildNav($totalCount, $page);

        if ($totalCount === 0) {
            return [];
        }

        $extractor = $this->getExtractor();
        if (!$extractor) {
            return [];
        }

        return $extractor->getElements($this->iblockCode, $filter, [
            'pageSize' => $this->pageSize,
            'page' => $page,
            'order' => $order,
        ]);
    }

    /**
     * Текущая страница из GET-параметра или параметров компонента
     */
    protected function getCurrentPage(): int
    {
        if (!empty($this->arParams['PAGE_NUM'])) {
            return max(1, (int)$this->arParams['PAGE_NUM']);
        }

        $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $page = (int)$request->get('page');

        return max(1, $page ?: 1);
    }

    /**
     * Сформировать данные навигации
     */
    private function buildNav(int $totalCount, int $currentPage): array
    {
        $totalPages = $this->pageSize > 0
            ? (int)ceil($totalCount / $this->pageSize)
            : 1;

        return [
            'TOTAL' => $totalCount,
            'PAGE' => $currentPage,
            'PAGE_SIZE' => $this->pageSize,
            'TOTAL_PAGES' => $totalPages,
            'HAS_PREV' => $currentPage > 1,
            'HAS_NEXT' => $currentPage < $totalPages,
        ];
    }
}
