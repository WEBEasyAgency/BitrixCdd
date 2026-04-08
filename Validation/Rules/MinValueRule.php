<?php

namespace BitrixCdd\Validation\Rules;

use BitrixCdd\Validation\ValidationRule;

/**
 * Правило валидации минимального значения числа
 */
class MinValueRule implements ValidationRule
{
    private float $minValue;
    private bool $inclusive;

    public function __construct(float $minValue, bool $inclusive = true)
    {
        $this->minValue = $minValue;
        $this->inclusive = $inclusive;
    }

    public function validate($value, array $context = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Пустое значение - это задача RequiredRule
        }

        $numericValue = is_numeric($value) ? floatval($value) : 0;

        if ($this->inclusive) {
            return $numericValue >= $this->minValue;
        } else {
            return $numericValue > $this->minValue;
        }
    }

    public function getErrorMessage(array $context = []): string
    {
        $fieldName = $context['field_name'] ?? 'Значение';

        if ($this->inclusive) {
            return sprintf('%s должно быть не меньше %s', $fieldName, $this->minValue);
        } else {
            return sprintf('%s должно быть больше %s', $fieldName, $this->minValue);
        }
    }
}
