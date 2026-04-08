<?php

namespace BitrixCdd\Infrastructure;

use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;
use BitrixCdd\Domain\Contracts\IBlockRegistrarInterface;
use BitrixCdd\Domain\Entities\IBlockEntity;

/**
 * Реализация регистратора инфоблоков для Bitrix
 */
class IBlockManager implements IBlockRegistrarInterface
{
    public function __construct()
    {
        Loader::includeModule('iblock');
    }

    /**
     * Регистрация инфоблока
     *
     * @param IBlockEntity $iblock
     * @return int
     * @throws \Exception
     */
    public function register(IBlockEntity $iblock): int
    {
        // Проверяем, существует ли инфоблок
        $existingId = $this->getIdByCode($iblock->getCode());
        if ($existingId) {
            return $existingId;
        }

        $iblockObject = new \CIBlock;
        $id = $iblockObject->Add($iblock->toArray());

        if (!$id) {
            throw new \Exception('Ошибка создания инфоблока: ' . $iblockObject->LAST_ERROR);
        }

        return (int)$id;
    }

    /**
     * Получение ID инфоблока по коду
     *
     * @param string $code
     * @return int|null
     */
    public function getIdByCode(string $code): ?int
    {
        $result = IblockTable::getList([
            'filter' => ['=CODE' => $code],
            'select' => ['ID'],
            'limit' => 1,
        ]);

        if ($row = $result->fetch()) {
            return (int)$row['ID'];
        }

        return null;
    }

    /**
     * Проверка существования инфоблока
     *
     * @param string $code
     * @return bool
     */
    public function exists(string $code): bool
    {
        return $this->getIdByCode($code) !== null;
    }

    /**
     * Удаление инфоблока
     *
     * @param string $code
     * @return bool
     */
    public function delete(string $code): bool
    {
        $id = $this->getIdByCode($code);
        if (!$id) {
            return false;
        }

        $connection = \Bitrix\Main\Application::getConnection();
        $connection->startTransaction();

        try {
            // CIBlock::Delete обеспечивает каскадное удаление элементов/разделов/свойств
            if (!\CIBlock::Delete($id)) {
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
     * Убедиться что инфоблок существует, создать если нет
     *
     * @param string $typeId ID типа инфоблока
     * @param string $code Код инфоблока
     * @param array $iblockData Данные инфоблока
     * @param bool $needSync Обновлять ли существующие инфоблоки
     * @return int ID инфоблока
     */
    public function ensureIblockExists(string $typeId, string $code, array $iblockData, bool $needSync = true, array $fields = []): int
    {
        // Проверяем существование
        $existingId = $this->getIdByCode($code);
        if ($existingId) {
            // Если существует и нужно синхронизировать - обновляем
            if ($needSync) {
                $this->updateIblock($existingId, array_merge(
                    ['IBLOCK_TYPE_ID' => $typeId],
                    $iblockData
                ));
                // Переустанавливаем права доступа при обновлении
                $this->setReadAccessViaAPI($existingId);
                // Устанавливаем настройки полей (транслитерация и т.д.)
                if (!empty($fields)) {
                    $this->setIblockFields($existingId, $fields);
                }
            }
            return $existingId;
        }

        // Готовим поля для создания
        $arFields = array_merge(
            [
                'IBLOCK_TYPE_ID' => $typeId,
                'CODE' => $code,
                'SITE_ID' => ['s1'],
                'ACTIVE' => 'Y',
            ],
            $iblockData
        );

        // Создаём инфоблок
        $iblockObject = new \CIBlock();
        $id = $iblockObject->Add($arFields);

        if (!$id) {
            throw new \Exception('Ошибка создания инфоблока ' . $code . ': ' . $iblockObject->LAST_ERROR);
        }

        // Устанавливаем права доступа через CIBlockRights API
        $this->setReadAccessViaAPI((int)$id);

        // Устанавливаем настройки полей (транслитерация и т.д.)
        if (!empty($fields)) {
            $this->setIblockFields((int)$id, $fields);
        }

        return (int)$id;
    }

    /**
     * Установить настройки полей инфоблока (транслитерация CODE и т.д.)
     *
     * @param int $iblockId ID инфоблока
     * @param array $fields Настройки полей
     */
    public function setIblockFields(int $iblockId, array $fields): void
    {
        \CIBlock::SetFields($iblockId, $fields);
    }

    /**
     * Установить право на чтение для группы "Все"
     *
     * @param int $iblockId ID инфоблока
     */
    private function setReadAccessViaAPI(int $iblockId): void
    {
        // CIBlock::SetPermission не имеет D7 аналога
        \CIBlock::SetPermission($iblockId, [2 => 'R']);
    }

    /**
     * Обновить данные существующего инфоблока
     *
     * @param int $iblockId ID инфоблока
     * @param array $iblockData Новые данные инфоблока
     */
    private function updateIblock(int $iblockId, array $iblockData): void
    {
        $iblockObject = new \CIBlock();
        $arFields = array_merge(
            [
                'ID' => $iblockId,
            ],
            $iblockData
        );

        // НЕ передаем GROUP_ID при обновлении, чтобы не перезаписывать вручную установленные права
        $iblockObject->Update($iblockId, $arFields);
    }
}
