<?php

namespace BitrixCdd\Validation\Rules;

use BitrixCdd\Validation\ValidationRule;

/**
 * Правило валидации максимальной длины строки
 */
class MaxLengthRule implements ValidationRule
{
    private int $maxLength;

    public function __construct(int $maxLength)
    {
        $this->maxLength = $maxLength;
    }

    public function validate($value, array $context = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Пустое значение - это задача RequiredRule
        }

        $stringValue = is_string($value) ? $value : (string)$value;

        // Очищаем HTML-теги перед подсчётом (важно для HTML-полей)
        $cleanValue = strip_tags($stringValue);

        return mb_strlen(trim($cleanValue)) <= $this->maxLength;
    }

    public function getErrorMessage(array $context = []): string
    {
        $fieldName = $context['field_name'] ?? 'Поле';
        return sprintf('%s не должно превышать %d символов', $fieldName, $this->maxLength);
    }
}
