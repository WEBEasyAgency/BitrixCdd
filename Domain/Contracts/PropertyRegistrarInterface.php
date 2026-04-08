<?php

namespace BitrixCdd\Domain\Contracts;

use BitrixCdd\Domain\Entities\PropertyEntity;

/**
 * Интерфейс регистратора свойств инфоблоков
 */
interface PropertyRegistrarInterface
{
    /**
     * Регистрация свойства
     *
     * @param int $iblockId ID инфоблока
     * @param PropertyEntity $property
     * @return int ID созданного свойства
     * @throws \Exception
     */
    public function register(int $iblockId, PropertyEntity $property): int;

    /**
     * Получение ID свойства по коду
     *
     * @param int $iblockId ID инфоблока
     * @param string $code
     * @return int|null
     */
    public function getIdByCode(int $iblockId, string $code): ?int;

    /**
     * Проверка существования свойства
     *
     * @param int $iblockId ID инфоблока
     * @param string $code
     * @return bool
     */
    public function exists(int $iblockId, string $code): bool;

    /**
     * Удаление свойства
     *
     * @param int $iblockId ID инфоблока
     * @param string $code
     * @return bool
     */
    public function delete(int $iblockId, string $code): bool;
}
