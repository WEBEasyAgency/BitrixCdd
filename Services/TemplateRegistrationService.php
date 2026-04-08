<?php

namespace BitrixCdd\Services;

use BitrixCdd\Domain\Contracts\TemplateRegistrarInterface;
use BitrixCdd\Domain\Entities\TemplateEntity;

/**
 * Сервис регистрации шаблонов
 * Application-слой для работы с шаблонами
 */
class TemplateRegistrationService
{
    /**
     * @param TemplateRegistrarInterface $registrar Регистратор шаблонов
     */
    public function __construct(
        private readonly TemplateRegistrarInterface $registrar
    ) {
    }

    /**
     * Регистрация шаблона
     *
     * @param TemplateEntity $template
     * @return bool
     * @throws \Exception
     */
    public function register(TemplateEntity $template): bool
    {
        try {
            return $this->registrar->register($template);
        } catch (\Exception $e) {
            throw new \Exception("Ошибка при регистрации шаблона '{$template->getTemplate()}': " . $e->getMessage());
        }
    }

    /**
     * Регистрация нескольких шаблонов
     *
     * @param array $templates Массив TemplateEntity
     * @return array Массив результатов регистрации
     */
    public function registerMultiple(array $templates): array
    {
        $results = [];

        foreach ($templates as $template) {
            if (!$template instanceof TemplateEntity) {
                continue;
            }

            try {
                $results[$template->getPath()] = $this->register($template);
            } catch (\Exception $e) {
                error_log($e->getMessage());
                $results[$template->getPath()] = false;
            }
        }

        return $results;
    }

    /**
     * Проверка существования шаблона
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return $this->registrar->exists($path);
    }
}
