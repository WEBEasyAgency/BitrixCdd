<?php

namespace BitrixCdd\Validation;

/**
 * Интерфейс правила валидации
 */
interface ValidationRule
{
    /**
     * Проверить значение
     *
     * @param mixed $value Значение для проверки
     * @param array $context Контекст (тип свойства, название и т.д.)
     * @return bool
     */
    public function validate($value, array $context = []): bool;

    /**
     * Получить текст ошибки
     *
     * @param array $context Контекст (название поля и т.д.)
     * @return string
     */
    public function getErrorMessage(array $context = []): string;
}
