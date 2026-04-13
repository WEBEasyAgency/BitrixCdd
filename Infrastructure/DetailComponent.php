<?php

namespace BitrixCdd\Infrastructure;

use Bitrix\Main\Loader;

/**
 * Компонент детальной страницы
 *
 * Получает один элемент по CODE или ID из параметров.
 * Устанавливает 404 если элемент не найден.
 *
 * class NewsDetailComponent extends DetailComponent {
 *     protected string $iblockCode = 'news';
 * }
 */
class DetailComponent extends BaseComponent
{
    /**
     * Получить элемент по CODE или ID
     * Возвращает трансформированные данные (через ElementDataExtractor) или null
     */
    protected function getItem(): ?array
    {
        $code = $this->arParams['ELEMENT_CODE'] ?? '';
        $id = (int)($this->arParams['ELEMENT_ID'] ?? 0);

        if ($code === '' && $id === 0) {
            return null;
        }

        $filter = ['ACTIVE' => 'Y'];
        if ($code !== '') {
            $filter['CODE'] = $code;
        } else {
            $filter['ID'] = $id;
        }

        $extractor = $this->getExtractor();
        if (!$extractor) {
            return null;
        }

        $items = $extractor->getElements($this->iblockCode, $filter, ['limit' => 1]);

        return $items[0] ?? null;
    }

    /**
     * Установить 404
     */
    protected function set404(): void
    {
        \Bitrix\Iblock\Component\Tools::process404(
            '',
            true,
            true,
            true
        );
    }

    /**
     * Пустой результат -- нет ключа ITEM (не ITEMS)
     */
    protected function isResultEmpty(): bool
    {
        return empty($this->arResult['ITEM']);
    }
}
