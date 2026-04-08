<?php

namespace BitrixCdd\Domain\Entities;

/**
 * Сущность шаблона сайта
 */
class TemplateEntity
{
    /**
     * @param string $path Путь для условия
     * @param string $template Название шаблона
     * @param string $condition Условие (exact, folder)
     * @param int $sort Сортировка
     * @param string $siteId ID сайта
     */
    public function __construct(
        private readonly string $path,
        private readonly string $template,
        private readonly string $condition = 'exact',
        private readonly int $sort = 100,
        private readonly string $siteId = 's1'
    ) {
    }

    /**
     * Получить путь
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Получить название шаблона
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Получить условие
     */
    public function getCondition(): string
    {
        return $this->condition;
    }

    /**
     * Получить сортировку
     */
    public function getSort(): int
    {
        return $this->sort;
    }

    /**
     * Получить ID сайта
     */
    public function getSiteId(): string
    {
        return $this->siteId;
    }

    /**
     * Преобразовать в массив для API Bitrix
     */
    public function toArray(): array
    {
        return [
            'SITE_ID' => $this->siteId,
            'CONDITION' => $this->condition,
            'TEMPLATE' => $this->template,
            'SORT' => $this->sort,
            'REAL_FILE' => $this->path,
        ];
    }
}
