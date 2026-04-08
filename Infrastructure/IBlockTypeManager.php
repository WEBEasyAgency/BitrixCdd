<?php

namespace BitrixCdd\Infrastructure;

use Bitrix\Main\Loader;
use Bitrix\Iblock\TypeTable;
use Bitrix\Iblock\TypeLanguageTable;

/**
 * Менеджер для работы с типами инфоблоков
 * Поддерживает создание, обновление и удаление типов с учетом strict_mode
 */
class IBlockTypeManager
{
    public function __construct()
    {
        Loader::includeModule('iblock');
    }

    /**
     * Синхронизировать типы инфоблоков из конфигурации
     *
     * @param array $configuredTypes Типы из конфигурации (id => config)
     * @param bool $strictMode Если true - удалять типы не в конфигурации
     * @param bool $needSync Если false - не обновлять существующие типы
     */
    public function syncTypes(array $configuredTypes, bool $strictMode = false, bool $needSync = true): void
    {
        // Получаем существующие типы
        $existingTypes = $this->getExistingTypes();
        $configuredIds = array_keys($configuredTypes);

        // Обновляем/создаем типы из конфигурации
        foreach ($configuredTypes as $typeId => $typeConfig) {
            if (isset($existingTypes[$typeId])) {
                // Тип существует - обновляем при needSync=true
                if ($needSync) {
                    $this->updateType($typeId, $typeConfig);
                }
            } else {
                // Тип не существует - создаем
                $this->createType($typeId, $typeConfig);
            }
        }

        // При strict_mode удаляем типы, которых нет в конфигурации
        if ($strictMode) {
            foreach ($existingTypes as $typeId => $typeData) {
                if (!in_array($typeId, $configuredIds)) {
                    $this->deleteType($typeId);
                }
            }
        }
    }

    /**
     * Получить все существующие типы из Bitrix
     */
    private function getExistingTypes(): array
    {
        $existingTypes = [];
        $result = TypeTable::getList([
            'select' => ['ID', 'SECTIONS', 'SORT'],
        ]);

        while ($type = $result->fetch()) {
            $existingTypes[$type['ID']] = $type;
        }

        return $existingTypes;
    }

    /**
     * Создать тип инфоблока
     */
    private function createType(string $typeId, array $config): void
    {
        $result = TypeTable::add([
            'ID' => $typeId,
            'SECTIONS' => $config['sections'] ?? 'Y',
        ]);

        if (!$result->isSuccess()) {
            $error = implode(', ', $result->getErrorMessages());
            error_log("IBlockTypeManager: Failed to create type '{$typeId}': {$error}");
            throw new \RuntimeException("Failed to create iblock type '{$typeId}': {$error}");
        }

        // Добавляем языковые данные
        if (isset($config['lang']) && is_array($config['lang'])) {
            foreach ($config['lang'] as $langId => $langData) {
                TypeLanguageTable::add([
                    'IBLOCK_TYPE_ID' => $typeId,
                    'LANGUAGE_ID' => $langId,
                    'NAME' => $langData['NAME'] ?? '',
                    'SECTION_NAME' => $langData['SECTION_NAME'] ?? '',
                    'ELEMENT_NAME' => $langData['ELEMENT_NAME'] ?? '',
                ]);
            }
        }
    }

    /**
     * Обновить тип инфоблока
     */
    private function updateType(string $typeId, array $config): void
    {
        TypeTable::update($typeId, [
            'SECTIONS' => $config['sections'] ?? 'Y',
        ]);

        // Обновляем языковые данные
        if (isset($config['lang']) && is_array($config['lang'])) {
            foreach ($config['lang'] as $langId => $langData) {
                // Проверяем существует ли запись
                $existing = TypeLanguageTable::getList([
                    'filter' => [
                        '=IBLOCK_TYPE_ID' => $typeId,
                        '=LANGUAGE_ID' => $langId,
                    ],
                ])->fetch();

                $fields = [
                    'NAME' => $langData['NAME'] ?? '',
                    'SECTION_NAME' => $langData['SECTION_NAME'] ?? '',
                    'ELEMENT_NAME' => $langData['ELEMENT_NAME'] ?? '',
                ];

                if ($existing) {
                    TypeLanguageTable::update(
                        ['IBLOCK_TYPE_ID' => $typeId, 'LANGUAGE_ID' => $langId],
                        $fields
                    );
                } else {
                    TypeLanguageTable::add(array_merge(
                        ['IBLOCK_TYPE_ID' => $typeId, 'LANGUAGE_ID' => $langId],
                        $fields
                    ));
                }
            }
        }
    }

    /**
     * Удалить тип инфоблока
     */
    private function deleteType(string $typeId): bool
    {
        $result = TypeTable::delete($typeId);
        return $result->isSuccess();
    }
}
