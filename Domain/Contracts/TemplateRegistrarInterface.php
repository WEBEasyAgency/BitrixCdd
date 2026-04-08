<?php

namespace BitrixCdd\Domain\Contracts;

use BitrixCdd\Domain\Entities\TemplateEntity;

/**
 * Интерфейс регистратора шаблонов сайта
 */
interface TemplateRegistrarInterface
{
    /**
     * Регистрация шаблона
     *
     * @param TemplateEntity $template
     * @return bool
     * @throws \Exception
     */
    public function register(TemplateEntity $template): bool;

    /**
     * Проверка существования шаблона для пути
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool;
}
