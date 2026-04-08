<?php

namespace BitrixCdd\Infrastructure;

use Bitrix\Main\Loader;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\PropertyEnumerationTable;
use BitrixCdd\Domain\Contracts\PropertyRegistrarInterface;
use BitrixCdd\Domain\Entities\PropertyEntity;

/**
 * Реализация регистратора свойств инфоблоков для Bitrix
 */
class PropertyManager implements PropertyRegistrarInterface
{
    public function __construct()
    {
        Loader::includeModule('iblock');
    }

    /**
     * Регистрация свойства
     *
     * @param int $iblockId ID инфоблока
     * @param PropertyEntity $property
     * @return int
     * @throws \Exception
     */
    public function register(int $iblockId, PropertyEntity $property): int
    {
        // Проверяем, существует ли свойство
        $existingId = $this->getIdByCode($iblockId, $property->getCode());
        if ($existingId) {
            return $existingId;
        }

        $propertyObject = new \CIBlockProperty;
        $id = $propertyObject->Add(array_merge([
            'IBLOCK_ID' => $iblockId,
        ], $property->toArray()));

        if (!$id) {
            throw new \Exception('Ошибка создания свойства: ' . $propertyObject->LAST_ERROR);
        }

        return (int)$id;
    }

    /**
     * Получение ID свойства по коду
     *
     * @param int $iblockId ID инфоблока
     * @param string $code
     * @return int|null
     */
    public function getIdByCode(int $iblockId, string $code): ?int
    {
        $result = PropertyTable::getList([
            'filter' => [
                '=IBLOCK_ID' => $iblockId,
                '=CODE' => $code,
            ],
            'select' => ['ID'],
            'limit' => 1,
        ]);

        if ($row = $result->fetch()) {
            return (int)$row['ID'];
        }

        return null;
    }

    /**
     * Проверка существования свойства
     *
     * @param int $iblockId ID инфоблока
     * @param string $code
     * @return bool
     */
    public function exists(int $iblockId, string $code): bool
    {
        return $this->getIdByCode($iblockId, $code) !== null;
    }

    /**
     * Удаление свойства
     *
     * @param int $iblockId ID инфоблока
     * @param string $code
     * @return bool
     */
    public function delete(int $iblockId, string $code): bool
    {
        $id = $this->getIdByCode($iblockId, $code);
        if (!$id) {
            return false;
        }

        $connection = \Bitrix\Main\Application::getConnection();
        $connection->startTransaction();

        try {
            $result = PropertyTable::delete($id);
            if (!$result->isSuccess()) {
                $connection->rollbackTransaction();
                return false;
            }

            $connection->commitTransaction();
            return true;
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            return false;
        }
    }

    /**
     * Добавить свойство инфоблока из массива конфигурации
     *
     * @param int $iblockId ID инфоблока
     * @param string $code Код свойства
     * @param array $data Данные свойства
     * @param bool $needSync Обновлять ли существующие свойства
     * @return int|null ID созданного свойства или null при ошибке
     */
    public function addProperty(int $iblockId, string $code, array $data, bool $needSync = true): ?int
    {
        // Проверяем, существует ли свойство
        $existingId = $this->getIdByCode($iblockId, $code);
        if ($existingId) {
            // Если существует и нужно синхронизировать - обновляем
            if ($needSync) {
                $this->updateProperty($existingId, $data);
            }
            return $existingId;
        }

        // Отделяем VALUES (enum значения) от остальных данных
        $enumValues = $data['VALUES'] ?? [];
        $createData = array_diff_key($data, ['VALUES' => null]);

        // Готовим поля для создания
        $arFields = array_merge([
            'IBLOCK_ID' => $iblockId,
            'CODE' => $code,
        ], $createData);

        $propertyObject = new \CIBlockProperty();
        $id = $propertyObject->Add($arFields);

        if (!$id) {
            return null;
        }

        // Если это enum свойство, добавляем его значения
        if (!empty($enumValues)) {
            $this->syncEnumValues((int)$id, $enumValues);
        }

        return (int)$id;
    }

    /**
     * Обновить свойство инфоблока
     *
     * @param int $propertyId ID свойства
     * @param array $data Новые данные свойства
     */
    private function updateProperty(int $propertyId, array $data): void
    {
        $propertyObject = new \CIBlockProperty();

        // Отделяем VALUES (enum значения) от остальных данных
        $enumValues = $data['VALUES'] ?? [];
        $updateData = array_diff_key($data, ['VALUES' => null]);

        $arFields = array_merge([
            'ID' => $propertyId,
        ], $updateData);

        $propertyObject->Update($propertyId, $arFields);

        // Если это enum свойство, обновляем его значения
        if (!empty($enumValues)) {
            $this->syncEnumValues($propertyId, $enumValues);
        }
    }

    /**
     * Синхронизировать enum значения свойства
     *
     * @param int $propertyId ID свойства
     * @param array $values Массив значений [['VALUE' => '...', 'SORT' => ...], ...]
     */
    private function syncEnumValues(int $propertyId, array $values): void
    {
        // Получаем существующие enum значения
        $existingEnums = [];
        $enumResult = PropertyEnumerationTable::getList([
            'filter' => ['=PROPERTY_ID' => $propertyId],
            'select' => ['ID', 'VALUE'],
        ]);
        while ($enum = $enumResult->fetch()) {
            $existingEnums[$enum['VALUE']] = (int)$enum['ID'];
        }

        $configValues = [];
        foreach ($values as $value) {
            $configValues[] = $value['VALUE'];
        }

        // Удаляем enum значения которых нет в конфиге
        foreach ($existingEnums as $value => $id) {
            if (!in_array($value, $configValues)) {
                PropertyEnumerationTable::delete(['ID' => $id, 'PROPERTY_ID' => $propertyId]);
            }
        }

        // Добавляем или обновляем enum значения
        foreach ($values as $value) {
            $enumValue = $value['VALUE'];
            if (isset($existingEnums[$enumValue])) {
                // Обновляем существующее значение
                PropertyEnumerationTable::update(
                    ['ID' => $existingEnums[$enumValue], 'PROPERTY_ID' => $propertyId],
                    [
                        'VALUE' => $enumValue,
                        'SORT' => $value['SORT'] ?? 100,
                        'DEF' => $value['DEF'] ?? 'N',
                    ]
                );
            } else {
                // Добавляем новое значение
                $xmlId = $value['XML_ID'] ?? md5($propertyId . '_' . $enumValue);
                PropertyEnumerationTable::add([
                    'PROPERTY_ID' => $propertyId,
                    'VALUE' => $enumValue,
                    'XML_ID' => $xmlId,
                    'SORT' => $value['SORT'] ?? 100,
                    'DEF' => $value['DEF'] ?? 'N',
                ]);
            }
        }
    }
}
