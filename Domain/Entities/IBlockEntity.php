<?php

namespace BitrixCdd\Domain\Entities;

/**
 * Сущность инфоблока
 */
class IBlockEntity
{
    /**
     * @param string $iblockTypeId ID типа инфоблока
     * @param string $code Символьный код
     * @param string $name Название
     * @param array $languageMessages Языковые сообщения
     * @param string $siteId ID сайта
     * @param int $sort Сортировка
     * @param array $additionalFields Дополнительные поля
     */
    public function __construct(
        private readonly string $iblockTypeId,
        private readonly string $code,
        private readonly string $name,
        private readonly array $languageMessages,
        private readonly string $siteId = 's1',
        private readonly int $sort = 100,
        private readonly array $additionalFields = []
    ) {
    }

    /**
     * Получить ID типа инфоблока
     */
    public function getIblockTypeId(): string
    {
        return $this->iblockTypeId;
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
     * Получить языковые сообщения
     */
    public function getLanguageMessages(): array
    {
        return $this->languageMessages;
    }

    /**
     * Получить ID сайта
     */
    public function getSiteId(): string
    {
        return $this->siteId;
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
            'IBLOCK_TYPE_ID' => $this->iblockTypeId,
            'CODE' => $this->code,
            'NAME' => $this->name,
            'SITE_ID' => [$this->siteId],
            'SORT' => $this->sort,
            'GROUP_ID' => [1 => 'R', 2 => 'W'], // Права доступа
            'WORKFLOW' => 'N',
        ], $this->languageMessages, $this->additionalFields);
    }
}
