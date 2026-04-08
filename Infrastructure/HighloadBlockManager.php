<?php

namespace BitrixCdd\Infrastructure;

use BitrixCdd\Domain\Contracts\HighloadBlockRegistrarInterface;
use BitrixCdd\Domain\Entities\HighloadBlockEntity;

/**
 * Реализация регистратора highload-блоков для Bitrix
 */
class HighloadBlockManager implements HighloadBlockRegistrarInterface
{
    /**
     * Регистрация highload-блока
     *
     * @param HighloadBlockEntity $hlblock
     * @return int
     * @throws \Exception
     */
    public function register(HighloadBlockEntity $hlblock): int
    {
        if (!\Bitrix\Main\Loader::includeModule('highloadblock')) {
            throw new \Exception('Модуль highloadblock не установлен');
        }

        // Проверяем, существует ли highload-блок
        $existingId = $this->getIdByName($hlblock->getName());
        if ($existingId) {
            return $existingId;
        }

        $result = \Bitrix\Highloadblock\HighloadBlockTable::add([
            'NAME' => $hlblock->getName(),
            'TABLE_NAME' => $hlblock->getTableName(),
        ]);

        if (!$result->isSuccess()) {
            throw new \Exception('Ошибка создания highload-блока: ' . implode(', ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    /**
     * Получение ID highload-блока по имени
     *
     * @param string $name
     * @return int|null
     */
    public function getIdByName(string $name): ?int
    {
        if (!\Bitrix\Main\Loader::includeModule('highloadblock')) {
            return null;
        }

        $result = \Bitrix\Highloadblock\HighloadBlockTable::getList([
            'filter' => ['NAME' => $name],
            'select' => ['ID']
        ]);

        if ($hlblock = $result->fetch()) {
            return (int)$hlblock['ID'];
        }

        return null;
    }

    /**
     * Проверка существования highload-блока
     *
     * @param string $name
     * @return bool
     */
    public function exists(string $name): bool
    {
        return $this->getIdByName($name) !== null;
    }

    /**
     * Удаление highload-блока
     *
     * @param string $name
     * @return bool
     */
    public function delete(string $name): bool
    {
        if (!\Bitrix\Main\Loader::includeModule('highloadblock')) {
            return false;
        }

        $id = $this->getIdByName($name);
        if (!$id) {
            return false;
        }

        try {
            $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById($id)->fetch();
            if ($hlblock) {
                \Bitrix\Highloadblock\HighloadBlockTable::delete($id);
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }
}
