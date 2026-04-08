<?php

namespace BitrixCdd\Domain\Contracts;

use BitrixCdd\Domain\Entities\HighloadBlockEntity;

/**
 * Интерфейс регистратора highload-блоков
 */
interface HighloadBlockRegistrarInterface
{
    /**
     * Регистрация highload-блока
     *
     * @param HighloadBlockEntity $hlblock
     * @return int ID созданного highload-блока
     * @throws \Exception
     */
    public function register(HighloadBlockEntity $hlblock): int;

    /**
     * Получение ID highload-блока по имени
     *
     * @param string $name
     * @return int|null
     */
    public function getIdByName(string $name): ?int;

    /**
     * Проверка существования highload-блока
     *
     * @param string $name
     * @return bool
     */
    public function exists(string $name): bool;

    /**
     * Удаление highload-блока
     *
     * @param string $name
     * @return bool
     */
    public function delete(string $name): bool;
}
