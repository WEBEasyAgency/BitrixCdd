<?php

namespace BitrixCdd\Services;

use BitrixCdd\Domain\Contracts\HighloadBlockRegistrarInterface;
use BitrixCdd\Domain\Entities\HighloadBlockEntity;

/**
 * Сервис регистрации хайлоад-блоков
 * Application-слой для работы с highload-блоками
 */
class HighloadBlockRegistrationService
{
    /**
     * @param HighloadBlockRegistrarInterface $registrar Регистратор хайлоад-блоков
     */
    public function __construct(
        private readonly HighloadBlockRegistrarInterface $registrar
    ) {
    }

    /**
     * Регистрация хайлоад-блока
     *
     * @param HighloadBlockEntity $hlblock
     * @return int
     * @throws \Exception
     */
    public function register(HighloadBlockEntity $hlblock): int
    {
        try {
            return $this->registrar->register($hlblock);
        } catch (\Exception $e) {
            throw new \Exception("Ошибка при регистрации highload-блока '{$hlblock->getName()}': " . $e->getMessage());
        }
    }

    /**
     * Регистрация нескольких хайлоад-блоков
     *
     * @param array $hlblocks Массив HighloadBlockEntity
     * @return array Массив ID зарегистрированных хайлоад-блоков
     */
    public function registerMultiple(array $hlblocks): array
    {
        $ids = [];

        foreach ($hlblocks as $hlblock) {
            if (!$hlblock instanceof HighloadBlockEntity) {
                continue;
            }

            try {
                $ids[$hlblock->getName()] = $this->register($hlblock);
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }

        return $ids;
    }

    /**
     * Получение ID хайлоад-блока по имени
     *
     * @param string $name
     * @return int|null
     */
    public function getIdByName(string $name): ?int
    {
        return $this->registrar->getIdByName($name);
    }

    /**
     * Проверка существования хайлоад-блока
     *
     * @param string $name
     * @return bool
     */
    public function exists(string $name): bool
    {
        return $this->registrar->exists($name);
    }

    /**
     * Удаление хайлоад-блока
     *
     * @param string $name
     * @return bool
     */
    public function delete(string $name): bool
    {
        return $this->registrar->delete($name);
    }
}
