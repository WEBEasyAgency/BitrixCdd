<?php

namespace BitrixCdd\Validation\Rules;

use BitrixCdd\Validation\ValidationRule;

/**
 * Правило валидации максимального количества значений множественного свойства
 */
class MaxCountRule implements ValidationRule
{
    private int $maxCount;

    public function __construct(int $maxCount)
    {
        $this->maxCount = $maxCount;
    }

    public function validate($value, array $context = []): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return true;
        }

        if (!is_array($value)) {
            return true; // Одиночное значение — всегда ок
        }

        return count($value) <= $this->maxCount;
    }

    public function getErrorMessage(array $context = []): string
    {
        $fieldName = $context['field_name'] ?? 'Поле';
        return sprintf('%s: максимум %d элементов', $fieldName, $this->maxCount);
    }
}
