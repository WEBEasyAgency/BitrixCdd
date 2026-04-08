<?php

namespace BitrixCdd\Domain\Contracts;

use BitrixCdd\Domain\Entities\IBlockEntity;

/**
 * Интерфейс регистратора инфоблоков
 */
interface IBlockRegistrarInterface
{
    /**
     * Регистрация инфоблока
     *
     * @param IBlockEntity $iblock
     * @return int ID созданного инфоблока
     * @throws \Exception
     */
    public function register(IBlockEntity $iblock): int;

    /**
     * Получение ID инфоблока по коду
     *
     * @param string $code
     * @return int|null
     */
    public function getIdByCode(string $code): ?int;

    /**
     * Проверка существования инфоблока
     *
     * @param string $code
     * @return bool
     */
    public function exists(string $code): bool;

    /**
     * Удаление инфоблока
     *
     * @param string $code
     * @return bool
     */
    public function delete(string $code): bool;
}
