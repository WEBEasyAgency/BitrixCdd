<?php

namespace BitrixCdd\Validation;

use BitrixCdd\Validation\Rules\MaxCountRule;
use BitrixCdd\Validation\Rules\MaxLengthRule;
use BitrixCdd\Validation\Rules\MinValueRule;
use BitrixCdd\Validation\Rules\RequiredFileRule;

/**
 * Валидатор свойств инфоблоков
 */
class PropertyValidator
{
    private array $rules = [];

    /**
     * Добавить правило валидации для свойства
     *
     * @param string $propertyCode Код свойства
     * @param ValidationRule $rule Правило валидации
     * @param string $fieldName Название поля для сообщений об ошибках
     */
    public function addRule(string $propertyCode, ValidationRule $rule, string $fieldName = ''): void
    {
        if (!isset($this->rules[$propertyCode])) {
            $this->rules[$propertyCode] = [];
        }

        $this->rules[$propertyCode][] = [
            'rule' => $rule,
            'field_name' => $fieldName,
        ];
    }

    /**
     * Создать правила из конфигурации свойства
     *
     * @param string $propertyCode Код свойства
     * @param array $config Конфигурация свойства
     */
    public function addRulesFromConfig(string $propertyCode, array $config): void
    {
        $fieldName = $config['name'] ?? $propertyCode;
        $validation = $config['validation'] ?? [];

        // Максимальная длина строки
        if (isset($validation['max_length'])) {
            $this->addRule(
                $propertyCode,
                new MaxLengthRule($validation['max_length']),
                $fieldName
            );
        }

        // Минимальное значение числа
        if (isset($validation['min'])) {
            $inclusive = $validation['min_inclusive'] ?? true;
            $this->addRule(
                $propertyCode,
                new MinValueRule($validation['min'], $inclusive),
                $fieldName
            );
        }

        // Максимальное количество значений (для множественных свойств)
        if (isset($validation['max_count'])) {
            $this->addRule(
                $propertyCode,
                new MaxCountRule($validation['max_count']),
                $fieldName
            );
        }

        // Обязательный файл
        if (isset($config['type']) && $config['type'] === 'F' && isset($config['required']) && $config['required']) {
            $this->addRule(
                $propertyCode,
                new RequiredFileRule(),
                $fieldName
            );
        }
    }

    /**
     * Валидировать свойства элемента
     *
     * @param array $propertyValues Массив значений свойств [ID => [значения]]
     * @param array $propertyMap Соответствие ID свойств к их кодам [ID => CODE]
     * @param int|null $elementId ID элемента (null для новых элементов)
     * @return array Массив ошибок валидации
     */
    public function validateProperties(array $propertyValues, array $propertyMap, ?int $elementId = null): array
    {
        $errors = [];

        foreach ($propertyValues as $propId => $propValues) {
            $propCode = $propertyMap[$propId] ?? null;
            if (!$propCode || !isset($this->rules[$propCode])) {
                continue;
            }

            // Получаем значения свойства
            $value = null;
            $allValues = [];
            if (is_array($propValues)) {
                foreach ($propValues as $val) {
                    if (isset($val['VALUE']) && $val['VALUE'] !== '' && $val['VALUE'] !== null) {
                        $v = $val['VALUE'];

                        // Для HTML-полей (user_type=HTML) Bitrix возвращает массив ['TEXT' => '...', 'TYPE' => 'html']
                        if (is_array($v) && isset($v['TEXT'])) {
                            $v = $v['TEXT'];
                        }

                        $allValues[] = $v;
                    }
                }
            }
            $value = !empty($allValues) ? $allValues[0] : null;

            // Проверяем все правила для этого свойства
            foreach ($this->rules[$propCode] as $ruleData) {
                $rule = $ruleData['rule'];
                $fieldName = $ruleData['field_name'];

                $context = [
                    'field_name' => $fieldName,
                    'property_code' => $propCode,
                ];

                if ($elementId !== null) {
                    $context['element_id'] = $elementId;
                }

                // MaxCountRule получает массив всех значений
                $validateValue = ($rule instanceof MaxCountRule) ? $allValues : $value;

                if (!$rule->validate($validateValue, $context)) {
                    $errors[] = $rule->getErrorMessage($context);
                }
            }
        }

        return $errors;
    }

    /**
     * Получить все правила
     *
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
