<?php

namespace BitrixCdd\Services;

use Bitrix\Main\EventManager;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\PropertyTable;
use BitrixCdd\Validation\PropertyValidator;

/**
 * Сервис для управления валидацией инфоблоков
 */
class ValidationService
{
    private array $validators = []; // [iblock_code => PropertyValidator]

    /**
     * Зарегистрировать валидатор для инфоблока
     *
     * @param string $iblockCode Код инфоблока
     * @param PropertyValidator $validator Валидатор
     */
    public function registerValidator(string $iblockCode, PropertyValidator $validator): void
    {
        $this->validators[$iblockCode] = $validator;
    }

    /**
     * Создать валидатор из конфигурации инфоблока
     *
     * @param string $iblockCode Код инфоблока
     * @param array $config Конфигурация инфоблока
     */
    public function registerFromConfig(string $iblockCode, array $config): void
    {
        $validator = new PropertyValidator();

        // Добавляем правила из конфигурации свойств
        if (isset($config['properties'])) {
            foreach ($config['properties'] as $propCode => $propConfig) {
                $validator->addRulesFromConfig($propCode, $propConfig);
            }
        }

        $this->registerValidator($iblockCode, $validator);
    }

    /**
     * Инициализировать обработчики событий для валидации
     */
    public function initEventHandlers(): void
    {
        $eventManager = EventManager::getInstance();

        // Регистрируем единый обработчик для всех инфоблоков
        $eventManager->addEventHandler('iblock', 'OnBeforeIBlockElementUpdate', function(&$arFields) {
            return $this->validateElement($arFields);
        });

        $eventManager->addEventHandler('iblock', 'OnBeforeIBlockElementAdd', function(&$arFields) {
            return $this->validateElement($arFields);
        });
    }

    /**
     * Валидировать элемент инфоблока
     *
     * @param array $arFields Поля элемента
     * @return bool
     */
    private function validateElement(&$arFields): bool
    {
        if (!isset($arFields['IBLOCK_ID'])) {
            return true;
        }

        // Получаем код инфоблока
        $iblock = IblockTable::getById($arFields['IBLOCK_ID'])->fetch();
        if (!$iblock) {
            return true;
        }

        $iblockCode = $iblock['CODE'];

        // Проверяем, есть ли валидатор для этого инфоблока
        if (!isset($this->validators[$iblockCode])) {
            return true;
        }

        // global $APPLICATION — легитимный паттерн Bitrix для OnBefore событий
        global $APPLICATION;

        // Валидация свойств
        if (!isset($arFields['PROPERTY_VALUES']) || empty($arFields['PROPERTY_VALUES'])) {
            return true;
        }

        // Получаем соответствие ID свойств к их кодам
        $propertyMap = [];
        $result = PropertyTable::getList([
            'filter' => ['=IBLOCK_ID' => $arFields['IBLOCK_ID']],
            'select' => ['ID', 'CODE'],
        ]);
        while ($prop = $result->fetch()) {
            $propertyMap[$prop['ID']] = $prop['CODE'];
        }

        // Валидируем свойства
        $validator = $this->validators[$iblockCode];
        $elementId = $arFields['ID'] ?? null;
        $errors = $validator->validateProperties($arFields['PROPERTY_VALUES'], $propertyMap, $elementId);

        // Если есть ошибки - выводим их
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $APPLICATION->ThrowException($error);
            }
            return false;
        }

        return true;
    }

    /**
     * Получить валидатор для инфоблока
     *
     * @param string $iblockCode Код инфоблока
     * @return PropertyValidator|null
     */
    public function getValidator(string $iblockCode): ?PropertyValidator
    {
        return $this->validators[$iblockCode] ?? null;
    }
}
