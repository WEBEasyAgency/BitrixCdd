<?php

namespace BitrixCdd\Domain\Entities;

/**
 * Сущность highload-блока
 */
class HighloadBlockEntity
{
    /**
     * @param string $name Название
     * @param string $tableName Имя таблицы
     * @param array $additionalFields Дополнительные поля
     */
    public function __construct(
        private readonly string $name,
        private readonly string $tableName,
        private readonly array $additionalFields = []
    ) {
    }

    /**
     * Получить название
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Получить имя таблицы
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Получить дополнительные поля
     */
    public function getAdditionalFields(): array
    {
        return $this->additionalFields;
    }

    /**
     * Преобразовать в массив для API Bitrix
     */
    public function toArray(): array
    {
        return array_merge([
            'NAME' => $this->name,
            'TABLE_NAME' => $this->tableName,
        ], $this->additionalFields);
    }
}
