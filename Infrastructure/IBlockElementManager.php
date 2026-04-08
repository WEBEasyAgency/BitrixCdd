<?php

namespace BitrixCdd\Infrastructure;

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

/**
 * Менеджер для работы с элементами инфоблоков
 * Низкоуровневая работа с API Bitrix
 */
class IBlockElementManager
{
    public function __construct()
    {
        Loader::includeModule('iblock');
    }

    /**
     * Создать элемент инфоблока
     *
     * @param int $iblockId ID инфоблока
     * @param array $fields Поля элемента (NAME, ACTIVE, PREVIEW_TEXT, etc.)
     * @param array $properties Свойства элемента [CODE => VALUE]
     * @return int|false ID созданного элемента или false при ошибке
     */
    public function createElement(int $iblockId, array $fields, array $properties = []): int|false
    {
        $el = new \CIBlockElement;

        // Подготовка полей
        $elementFields = array_merge([
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
        ], $fields);

        // Добавление свойств
        if (!empty($properties)) {
            $elementFields['PROPERTY_VALUES'] = $properties;
        }

        $elementId = $el->Add($elementFields);

        if (!$elementId) {
            error_log("Error creating element: " . $el->LAST_ERROR);
            return false;
        }

        return $elementId;
    }

    /**
     * Обновить элемент инфоблока
     *
     * @param int $elementId ID элемента
     * @param array $fields Поля для обновления
     * @param array $properties Свойства для обновления
     * @return bool
     */
    public function updateElement(int $elementId, array $fields = [], array $properties = []): bool
    {
        $el = new \CIBlockElement;

        // Обновление полей
        if (!empty($fields)) {
            $result = $el->Update($elementId, $fields);
            if (!$result) {
                error_log("Error updating element: " . $el->LAST_ERROR);
                return false;
            }
        }

        // Обновление свойств
        if (!empty($properties)) {
            $result = $el->Update($elementId, ['PROPERTY_VALUES' => $properties]);

            if (!$result) {
                error_log("Error updating properties: " . $el->LAST_ERROR);
                return false;
            }
        }

        return true;
    }

    /**
     * Удалить элемент инфоблока
     *
     * @param int $elementId ID элемента
     * @return bool
     */
    public function deleteElement(int $elementId): bool
    {
        $connection = \Bitrix\Main\Application::getConnection();
        $connection->startTransaction();

        try {
            $result = \CIBlockElement::Delete($elementId);
            if (!$result) {
                throw new \Exception("Failed to delete element {$elementId}");
            }
            $connection->commitTransaction();
            return true;
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            error_log("Error deleting element: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить элементы инфоблока
     * CIBlockElement::GetList + GetNextElement/GetProperties используется т.к.
     * D7 Iblock::wakeUp() требует знание всех кодов свойств заранее и возвращает
     * данные в принципиально ином формате. GetProperties() — единственный способ
     * получить полный набор свойств без перечисления.
     *
     * @param int $iblockId ID инфоблока
     * @param array $filter Фильтр выборки
     * @param array $select Поля для выборки
     * @param array $order Сортировка
     * @param array $navParams Параметры постраничной навигации
     * @return array
     */
    public function getElements(
        int $iblockId,
        array $filter = [],
        array $select = [],
        array $order = ['SORT' => 'ASC'],
        array $navParams = []
    ): array {
        $filter['IBLOCK_ID'] = $iblockId;

        if (empty($select)) {
            $select = ['ID', 'NAME', 'ACTIVE', 'SORT', 'DATE_CREATE', 'IBLOCK_ID', 'PROPERTY_*'];
        } elseif (!in_array('IBLOCK_ID', $select)) {
            $select[] = 'IBLOCK_ID';
        }

        $result = [];
        $res = \CIBlockElement::GetList($order, $filter, false, $navParams ?: false, $select);

        while ($element = $res->GetNextElement()) {
            $fields = $element->GetFields();
            $props = $element->GetProperties();

            if (!isset($fields['IBLOCK_ID'])) {
                $fields['IBLOCK_ID'] = $iblockId;
            }

            $result[] = [
                'fields' => $fields,
                'properties' => $props,
            ];
        }

        return $result;
    }

    /**
     * Получить элемент по ID
     *
     * @param int $elementId ID элемента
     * @param array $select Поля для выборки
     * @return array|null
     */
    public function getElementById(int $elementId, array $select = []): ?array
    {
        if (empty($select)) {
            $select = ['ID', 'IBLOCK_ID', 'NAME', 'ACTIVE', 'SORT', 'PROPERTY_*'];
        }

        $res = \CIBlockElement::GetList(
            [],
            ['ID' => $elementId],
            false,
            false,
            $select
        );

        if ($element = $res->GetNextElement()) {
            return [
                'fields' => $element->GetFields(),
                'properties' => $element->GetProperties(),
            ];
        }

        return null;
    }

    /**
     * Найти элемент по названию
     *
     * @param int $iblockId ID инфоблока
     * @param string $name Название элемента
     * @return int|null ID элемента или null
     */
    public function findElementByName(int $iblockId, string $name): ?int
    {
        $result = ElementTable::getList([
            'filter' => ['=IBLOCK_ID' => $iblockId, '=NAME' => $name],
            'select' => ['ID'],
            'limit' => 1,
        ]);

        if ($row = $result->fetch()) {
            return (int)$row['ID'];
        }

        return null;
    }

    /**
     * Найти элемент по символьному коду
     *
     * @param int $iblockId ID инфоблока
     * @param string $code Символьный код элемента
     * @return int|null ID элемента или null
     */
    public function findElementByCode(int $iblockId, string $code): ?int
    {
        $result = ElementTable::getList([
            'filter' => ['=IBLOCK_ID' => $iblockId, '=CODE' => $code],
            'select' => ['ID'],
            'limit' => 1,
        ]);

        if ($row = $result->fetch()) {
            return (int)$row['ID'];
        }

        return null;
    }

    /**
     * Получить все элементы с символьными кодами
     *
     * @param int $iblockId ID инфоблока
     * @return array Массив элементов [CODE => ID]
     */
    public function getElementsWithCodes(int $iblockId): array
    {
        $elements = [];
        $result = ElementTable::getList([
            'filter' => [
                '=IBLOCK_ID' => $iblockId,
                '!=CODE' => '',
            ],
            'select' => ['ID', 'CODE'],
        ]);

        while ($row = $result->fetch()) {
            if (!empty($row['CODE'])) {
                $elements[$row['CODE']] = (int)$row['ID'];
            }
        }

        return $elements;
    }

    /**
     * Проверить существование элемента
     *
     * @param int $elementId ID элемента
     * @return bool
     */
    public function elementExists(int $elementId): bool
    {
        $result = ElementTable::getList([
            'filter' => ['=ID' => $elementId],
            'select' => ['ID'],
            'limit' => 1,
        ]);

        return (bool)$result->fetch();
    }

    /**
     * Получить количество элементов в инфоблоке
     *
     * @param int $iblockId ID инфоблока
     * @param array $filter Дополнительный фильтр
     * @return int
     */
    public function getElementsCount(int $iblockId, array $filter = []): int
    {
        $filter['=IBLOCK_ID'] = $iblockId;

        return ElementTable::getCount($filter);
    }
}
