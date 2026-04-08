<?php

namespace BitrixCdd\Domain\Entities;

/**
 * Сущность свойства инфоблока
 */
class PropertyEntity
{
    /**
     * @param string $code Символьный код
     * @param string $name Название
     * @param string $propertyType Тип свойства (S, F, L, N, E, G)
     * @param bool $isRequired Обязательность
     * @param bool $multiple Множественность
     * @param int $sort Сортировка
     * @param array $additionalFields Дополнительные поля
     */
    public function __construct(
        private readonly string $code,
        private readonly string $name,
        private readonly string $propertyType,
        private readonly bool $isRequired = false,
        private readonly bool $multiple = false,
        private readonly int $sort = 100,
        private readonly array $additionalFields = []
    ) {
    }

    /**
     * Получить символьный код
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Получить название
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Получить тип свойства
     */
    public function getPropertyType(): string
    {
        return $this->propertyType;
    }

    /**
     * Проверить обязательность
     */
    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    /**
     * Проверить множественность
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Получить сортировку
     */
    public function getSort(): int
    {
        return $this->sort;
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
            'ACTIVE' => 'Y',
            'SORT' => $this->sort,
            'CODE' => $this->code,
            'PROPERTY_TYPE' => $this->propertyType,
            'MULTIPLE' => $this->multiple ? 'Y' : 'N',
            'IS_REQUIRED' => $this->isRequired ? 'Y' : 'N',
            'FILTRABLE' => 'Y',
            'SEARCHABLE' => 'N',
        ], $this->additionalFields);
    }
}
