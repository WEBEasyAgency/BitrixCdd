<?php

namespace BitrixCdd\Validation\Rules;

use Bitrix\Iblock\ElementTable;
use BitrixCdd\Validation\ValidationRule;

/**
 * Правило валидации обязательного файла
 */
class RequiredFileRule implements ValidationRule
{
    public function validate($value, array $context = []): bool
    {
        // Проверяем, загружен ли новый файл
        if (is_array($value) && !empty($value['tmp_name'])) {
            return true;
        }

        // Если это редактирование, проверяем наличие существующего файла
        if (isset($context['element_id']) && isset($context['property_code'])) {
            // CIBlockElement::GetProperty не имеет D7 аналога
            $propRes = \CIBlockElement::GetProperty(
                0,
                $context['element_id'],
                [],
                ['CODE' => $context['property_code']]
            );
            if ($propRes) {
                while ($prop = $propRes->Fetch()) {
                    if (!empty($prop['VALUE'])) {
                        return true;
                    }
                }
            }
        }

        // При создании нового элемента файл обязателен
        if (!isset($context['element_id'])) {
            return false;
        }

        return false;
    }

    public function getErrorMessage(array $context = []): string
    {
        $fieldName = $context['field_name'] ?? 'Файл';
        return sprintf('Необходимо загрузить %s', mb_strtolower($fieldName));
    }
}
