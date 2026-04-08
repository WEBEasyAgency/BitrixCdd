<?php

namespace BitrixCdd\Services;

use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\TypeTable;
use BitrixCdd\Infrastructure\IBlockBuilder;
use BitrixCdd\Infrastructure\IBlockBuilderFactory;
use BitrixCdd\Infrastructure\IBlockElementManager;
use BitrixCdd\Infrastructure\IBlockTypeManager;
use BitrixCdd\Infrastructure\VersionTracker;

/**
 * Сервис управления инфоблоками через конфигурацию
 * Загружает конфиги и регистрирует всё через IBlockBuilder
 */
class IBlockConfigService
{
    private array $configs = [];
    private array $globalConfig = [];
    private array $typeConfigs = [];
    private IBlockBuilder $builder;
    private IBlockElementManager $elementManager;
    private IBlockBuilderFactory $builderFactory;
    private IBlockTypeManager $typeManager;
    private VersionTracker $versionTracker;
    private PropertyValueConverter $valueConverter;
    private string $configDir;

    public function __construct(
        IBlockElementManager $elementManager,
        IBlockBuilderFactory $builderFactory,
        IBlockTypeManager $typeManager,
        VersionTracker $versionTracker,
        PropertyValueConverter $valueConverter,
        string $configDir = ''
    ) {
        $this->elementManager = $elementManager;
        $this->builderFactory = $builderFactory;
        $this->typeManager = $typeManager;
        $this->versionTracker = $versionTracker;
        $this->valueConverter = $valueConverter;
        $this->configDir = $configDir ?: ($_SERVER['DOCUMENT_ROOT'] . '/local/config');
    }

    /**
     * Загрузить конфигурации инфоблоков (без синхронизации с БД)
     * Используется при создании экземпляра для доступа к metadata
     */
    public function loadConfigs(): void
    {
        // Загружаем глобальные настройки
        $globalConfigPath = $this->configDir . '/global.php';
        if (file_exists($globalConfigPath)) {
            $this->globalConfig = require $globalConfigPath;
        }

        // Создаем builder
        $needSync = $this->globalConfig['need_sync'] ?? true;
        $this->builder = $this->builderFactory->create($needSync);

        // Загружаем конфигурации инфоблоков
        $iblockConfigDir = $this->configDir . '/iblocks';
        if (is_dir($iblockConfigDir)) {
            $this->loadConfigsFromDirectory($iblockConfigDir);
        }
    }

    /**
     * Загрузить и регистрировать ВСЁ из конфигурации
     */
    public function registerAll(): void
    {
        // 1. Загружаем глобальные настройки
        $globalConfigPath = $this->configDir . '/global.php';
        if (file_exists($globalConfigPath)) {
            $this->globalConfig = require $globalConfigPath;
        }

        // Создаем builder с нужным значением needSync
        $needSync = $this->globalConfig['need_sync'] ?? true;
        $this->builder = $this->builderFactory->create($needSync);

        // 2. Загружаем конфигурации типов инфоблоков
        $typeConfigDir = $this->configDir . '/iblock_types';
        if (is_dir($typeConfigDir)) {
            $this->loadTypeConfigsFromDirectory($typeConfigDir);
        }

        // 3. Синхронизируем типы инфоблоков
        $this->syncTypes();

        // 4. Загружаем конфигурации инфоблоков
        $iblockConfigDir = $this->configDir . '/iblocks';
        if (is_dir($iblockConfigDir)) {
            $this->loadConfigsFromDirectory($iblockConfigDir);
        }

        // 5. Регистрируем все инфоблоки и их контент
        $this->registerAllIblocks();

        // 6. Удаляем лишние инфоблоки при strict_mode
        $this->deleteExtraIblocks();
    }

    /**
     * Загрузить конфигурации типов инфоблоков из директории
     */
    private function loadTypeConfigsFromDirectory(string $directory): void
    {
        $files = glob($directory . '/*.php');
        foreach ($files as $file) {
            $this->loadTypeConfigFromFile($file);
        }
    }

    /**
     * Загрузить конфиг типа инфоблока
     */
    private function loadTypeConfigFromFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        try {
            $config = require $filePath;
            if (!is_array($config)) {
                return;
            }

            $typeId = $config['id'] ?? null;
            if ($typeId) {
                $this->typeConfigs[$typeId] = $config;
            }
        } catch (\Exception $e) {
            error_log("IBlockConfigService: Failed to load type config '{$filePath}': " . $e->getMessage());
        }
    }

    /**
     * Синхронизировать типы инфоблоков
     */
    private function syncTypes(): void
    {
        if (empty($this->typeConfigs)) {
            return;
        }

        $strictMode = $this->globalConfig['strict'] ?? false;
        $needSync = $this->globalConfig['need_sync'] ?? true;

        $this->typeManager->syncTypes($this->typeConfigs, $strictMode, $needSync);
    }

    /**
     * Удалить инфоблоки которых нет в конфигурации при strict_mode
     */
    private function deleteExtraIblocks(): void
    {
        $strictMode = $this->globalConfig['strict'] ?? false;

        if (!$strictMode) {
            return;
        }

        // Определяем типы инфоблоков из конфигурации — удаляем только инфоблоки этих типов
        $managedTypes = array_keys($this->typeConfigs);
        if (empty($managedTypes)) {
            // Нет типов в конфигурации — не удаляем ничего, чтобы не затронуть системные инфоблоки
            return;
        }

        // Получаем существующие инфоблоки только управляемых типов
        $existingIblocks = [];
        $result = IblockTable::getList([
            'filter' => ['=IBLOCK_TYPE_ID' => $managedTypes],
            'select' => ['ID', 'CODE'],
        ]);
        while ($iblock = $result->fetch()) {
            $existingIblocks[$iblock['CODE']] = $iblock;
        }

        $configuredCodes = array_keys($this->configs);

        // При strict_mode удаляем инфоблоки управляемых типов, которых нет в конфигурации
        foreach ($existingIblocks as $code => $iblock) {
            if (!in_array($code, $configuredCodes)) {
                // CIBlock::Delete обеспечивает каскадное удаление
                \CIBlock::Delete($iblock['ID']);
            }
        }
    }

    /**
     * Загрузить конфигурации инфоблоков из директории
     */
    private function loadConfigsFromDirectory(string $directory): void
    {
        $files = glob($directory . '/*.php');
        foreach ($files as $file) {
            $this->loadConfigFromFile($file);
        }
    }

    /**
     * Загрузить конфиг инфоблока
     */
    private function loadConfigFromFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        try {
            $config = require $filePath;
            if (!is_array($config)) {
                return;
            }

            // Применяем глобальные настройки
            $config = $this->mergeWithGlobalConfig($config);

            $code = $config['iblock']['code'] ?? null;
            if ($code) {
                $this->configs[$code] = $config;
            }
        } catch (\Exception $e) {
            error_log("IBlockConfigService: Failed to load config '{$filePath}': " . $e->getMessage());
        }
    }

    /**
     * Регистрировать все инфоблоки из загруженных конфигов
     * Сортирует по priority перед регистрацией
     */
    private function registerAllIblocks(): void
    {
        // Сортируем конфиги по priority (меньше = раньше)
        uasort($this->configs, function($a, $b) {
            $priorityA = $a['priority'] ?? 500;
            $priorityB = $b['priority'] ?? 500;
            return $priorityA <=> $priorityB;
        });

        foreach ($this->configs as $code => $config) {
            $this->registerIblock($code, $config);
        }
    }

    /**
     * Регистрировать один инфоблок со всем контентом
     * Использует версионную логику для оптимизации
     */
    public function registerIblock(string $code, array $config): void
    {
        try {
            $version = $config['version'] ?? '1.0';
            $needSync = $config['need_sync'] ?? $this->globalConfig['need_sync'] ?? false;
            $strict = $config['strict'] ?? $this->globalConfig['strict'] ?? false;

            $iblockId = null;
            $isVersionRegistered = $this->versionTracker->isVersionRegistered($code, $version);
            $isDemoSynced = $this->versionTracker->isDemoSynced($code, $version);

            // Логика на основе флагов need_sync и strict:
            if ($strict) {
                if ($needSync) {
                    // need_sync=true, strict=true: ВСЕГДА синхронизировать структуру И элементы
                    $iblockId = $this->syncIblockStructure($code, $config);
                    $this->deleteExtraProperties($iblockId, $config);
                    $this->syncDemoDataWithForce($iblockId, $config);
                    $this->deleteExtraElements($iblockId, $config);
                    // Версию не регистрируем - всегда синхронизируем
                } else {
                    // need_sync=false, strict=true: Синхронизировать только структуру, НЕ элементы
                    if (!$isVersionRegistered) {
                        $iblockId = $this->syncIblockStructure($code, $config);
                        $this->deleteExtraProperties($iblockId, $config);
                        $this->versionTracker->registerVersion($code, $version, false); // demo_synced=false
                    }
                    // Элементы НЕ трогаем
                }
            } else {
                if ($needSync) {
                    // need_sync=true, strict=false: Создать demo элементы если НЕ синхронизированы
                    // Разделяем синхронизацию структуры и demo-данных
                    if (!$isVersionRegistered) {
                        $iblockId = $this->syncIblockStructure($code, $config);
                        $this->versionTracker->registerVersion($code, $version, false); // Сначала регистрируем структуру
                    }

                    // Проверяем demo_synced отдельно
                    if (!$isDemoSynced) {
                        // Получаем ID инфоблока
                        $iblockId = $this->getIblockIdByCode($code);
                        if ($iblockId) {
                            $this->createDemoDataOnce($iblockId, $config);
                            $this->versionTracker->markDemoSynced($code, $version);
                        }
                    }
                } else {
                    // need_sync=false, strict=false: Создать только структуру (БЕЗ demo элементов)
                    if (!$isVersionRegistered) {
                        $this->syncIblockStructure($code, $config);
                        $this->versionTracker->registerVersion($code, $version, false); // demo_synced=false
                    }
                    // Демо-элементы НЕ создаём
                }
            }
            // Применяем iblock_fields (транслитерация и т.д.) всегда, независимо от версии
            if (!empty($config['iblock_fields'])) {
                $iblockId = $iblockId ?? $this->getIblockIdByCode($code);
                if ($iblockId) {
                    $iblockManager = $this->builderFactory->getIBlockManager();
                    $iblockManager->setIblockFields($iblockId, $config['iblock_fields']);
                }
            }
        } catch (\Exception $e) {
            // Логируем ошибку
            error_log("IBlockConfigService: Failed to register iblock '{$code}': " . $e->getMessage());
        }
    }

    /**
     * Подготовить конфиг для IBlockBuilder из текущего формата
     */
    private function prepareBuilderConfig(array $config): array
    {
        $typeId = $config['iblock']['type'] ?? 'vooz_content';
        $code = $config['iblock']['code'] ?? '';
        $name = $config['iblock']['name'] ?? $code;

        $builderConfig = [
            'type' => [
                'id' => $typeId,
            ],
            'iblock' => [
                'code' => $code,
                'data' => [
                    'NAME' => $name,
                    'SORT' => $config['iblock']['sort'] ?? 500,
                ],
            ],
            'properties' => $this->prepareProperties($config['properties'] ?? []),
        ];

        if (!empty($config['iblock_fields'])) {
            $builderConfig['iblock_fields'] = $config['iblock_fields'];
        }

        return $builderConfig;
    }

    /**
     * Подготовить свойства для IBlockBuilder
     */
    private function prepareProperties(array $properties): array
    {
        $prepared = [];

        foreach ($properties as $propCode => $propConfig) {
            $prepared[$propCode] = [
                'NAME' => $propConfig['name'] ?? $propCode,
                'PROPERTY_TYPE' => $this->mapPropertyType($propConfig['type'] ?? 'S'),
                'IS_REQUIRED' => ($propConfig['required'] ?? false) ? 'Y' : 'N',
                'MULTIPLE' => ($propConfig['multiple'] ?? false) ? 'Y' : 'N',
                'SORT' => $propConfig['sort'] ?? 100,
                'USER_TYPE' => $propConfig['user_type'] ?? '',
            ];

            // Обработка VALUES для enum (L)
            if (!empty($propConfig['VALUES'])) {
                $prepared[$propCode]['VALUES'] = $propConfig['VALUES'];
            }

            // Обработка LINK_IBLOCK_ID для типов E и G
            if (!empty($propConfig['link_iblock_id'])) {
                $linkIblockId = $this->getIblockIdByCode($propConfig['link_iblock_id']);
                if ($linkIblockId) {
                    $prepared[$propCode]['LINK_IBLOCK_ID'] = $linkIblockId;
                }
            }

            // Обработка FILE_TYPE для файловых свойств (F)
            if (!empty($propConfig['file_type'])) {
                $prepared[$propCode]['FILE_TYPE'] = $propConfig['file_type'];
            }
        }

        return $prepared;
    }

    /**
     * Получить ID инфоблока по коду
     */
    private function getIblockIdByCode(string $code): ?int
    {
        $result = IblockTable::getList([
            'filter' => ['=CODE' => $code],
            'select' => ['ID'],
            'limit' => 1,
        ]);

        if ($row = $result->fetch()) {
            return (int)$row['ID'];
        }

        return null;
    }

    /**
     * Маппинг типов свойств
     */
    private function mapPropertyType(string $type): string
    {
        $map = [
            'S' => 'S', // String
            'N' => 'N', // Number
            'L' => 'L', // List
            'F' => 'F', // File
            'T' => 'S', // Text (в Bitrix это тоже S с user_type)
            'E' => 'E', // Element (привязка к элементу инфоблока)
            'G' => 'G', // Section (привязка к разделу инфоблока)
        ];

        return $map[$type] ?? 'S';
    }

    /**
     * Синхронизировать только структуру инфоблока (iblock + properties)
     * Без демо-элементов
     *
     * @return int ID инфоблока
     */
    private function syncIblockStructure(string $code, array $config): int
    {
        $builderConfig = $this->prepareBuilderConfig($config);
        return $this->builder->build($builderConfig);
    }

    /**
     * Синхронизировать демо-данные с принудительным обновлением
     * Используется для strict=true, need_sync=true
     * Обновляет существующие элементы или создаёт новые
     */
    private function syncDemoDataWithForce(int $iblockId, array $config): void
    {
        if (empty($config['demo_data'])) {
            return;
        }

        $demoData = $config['demo_data'];
        $existingElements = $this->elementManager->getElementsWithCodes($iblockId);

        foreach ($demoData as $data) {
            $code = $data['code'] ?? '';
            $name = $data['name'] ?? '';

            if (empty($code) || empty($name)) {
                continue;
            }

            $fields = $this->prepareDemoFields($data);
            $properties = $this->prepareDemoProperties($data, $config, $iblockId);

            // Обновить существующий или создать новый
            if (isset($existingElements[$code])) {
                $elementId = $existingElements[$code];
                $this->elementManager->updateElement($elementId, $fields, $properties);
                unset($existingElements[$code]);
            } else {
                $this->elementManager->createElement($iblockId, $fields, $properties);
            }
        }
    }

    /**
     * Создать демо-данные только если они не существуют
     * Используется для strict=false, need_sync=true (первая регистрация версии)
     * НЕ обновляет существующие элементы
     */
    private function createDemoDataOnce(int $iblockId, array $config): void
    {
        if (empty($config['demo_data'])) {
            return;
        }

        $demoData = $config['demo_data'];
        $existingElements = $this->elementManager->getElementsWithCodes($iblockId);

        foreach ($demoData as $data) {
            $code = $data['code'] ?? '';
            $name = $data['name'] ?? '';

            if (empty($code) || empty($name)) {
                continue;
            }

            // Создать только если НЕ существует
            if (!isset($existingElements[$code])) {
                $fields = $this->prepareDemoFields($data);
                $properties = $this->prepareDemoProperties($data, $config, $iblockId);
                $this->elementManager->createElement($iblockId, $fields, $properties);
            }
        }
    }

    /**
     * Удалить свойства, которых нет в конфигурации
     * Используется для strict=true
     */
    private function deleteExtraProperties(int $iblockId, array $config): void
    {
        // Коды свойств из конфигурации
        $configPropertyCodes = array_keys($config['properties'] ?? []);

        // Получаем все существующие свойства
        $result = PropertyTable::getList([
            'filter' => ['=IBLOCK_ID' => $iblockId],
            'select' => ['ID', 'CODE'],
        ]);

        while ($property = $result->fetch()) {
            $propCode = $property['CODE'];

            // Если свойство есть в инфоблоке, но не в конфигурации - удаляем
            if (!in_array($propCode, $configPropertyCodes)) {
                PropertyTable::delete($property['ID']);
            }
        }
    }

    /**
     * Удалить элементы, которых нет в конфигурации
     * Используется для strict=true
     */
    private function deleteExtraElements(int $iblockId, array $config): void
    {
        if (empty($config['demo_data'])) {
            // Если demo_data пустой, удаляем ВСЕ элементы
            $allElements = $this->elementManager->getElements($iblockId);
            foreach ($allElements as $element) {
                $this->elementManager->deleteElement($element['fields']['ID']);
            }
            return;
        }

        // Получаем коды из конфигурации
        $configCodes = [];
        foreach ($config['demo_data'] as $data) {
            if (!empty($data['code'])) {
                $configCodes[] = $data['code'];
            }
        }

        // Получаем существующие элементы
        $existingElements = $this->elementManager->getElementsWithCodes($iblockId);

        // Удаляем элементы не из конфигурации
        foreach ($existingElements as $code => $elementId) {
            if (!in_array($code, $configCodes)) {
                $this->elementManager->deleteElement($elementId);
            }
        }
    }

    /**
     * Подготовить поля для демо-элемента
     */
    private function prepareDemoFields(array $data): array
    {
        $fields = [
            'NAME' => $data['name'] ?? '',
            'CODE' => $data['code'] ?? '',
            'XML_ID' => $data['xml_id'] ?? $data['code'] ?? '',
            'ACTIVE' => $data['active'] ?? 'Y',
            'SORT' => $data['sort'] ?? 500,
            'PREVIEW_TEXT' => $data['preview_text'] ?? '',
            'DETAIL_TEXT' => $data['detail_text'] ?? '',
        ];

        // Обработка файловых полей (PREVIEW_PICTURE, DETAIL_PICTURE)
        // Передаем массив CFile::MakeFileArray вместо ID для корректного обновления
        if (!empty($data['preview_picture'])) {
            $fileArray = $this->makeFileArrayFromPath($data['preview_picture']);
            if ($fileArray) {
                $fields['PREVIEW_PICTURE'] = $fileArray;
            }
        }

        if (!empty($data['detail_picture'])) {
            $fileArray = $this->makeFileArrayFromPath($data['detail_picture']);
            if ($fileArray) {
                $fields['DETAIL_PICTURE'] = $fileArray;
            }
        }

        return $fields;
    }

    /**
     * Создать массив файла из пути для передачи в Add/Update элемента
     *
     * @param string $filePath Путь к файлу (относительно DOCUMENT_ROOT)
     * @return array|null Массив файла или null при ошибке
     */
    private function makeFileArrayFromPath(string $filePath): ?array
    {
        $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $filePath;

        if (!file_exists($absolutePath)) {
            error_log("makeFileArrayFromPath: file not found - $absolutePath");
            return null;
        }

        $fileArray = \CFile::MakeFileArray($absolutePath);
        if (!$fileArray) {
            error_log("makeFileArrayFromPath: CFile::MakeFileArray failed for $absolutePath");
            return null;
        }

        return $fileArray;
    }

    /**
     * Подготовить свойства для демо-элемента
     * Использует PropertyValueConverter для преобразования значений
     *
     * @param array $data Данные демо-элемента
     * @param array $config Полная конфигурация инфоблока
     * @param int $iblockId ID инфоблока (для enum lookup)
     * @return array Подготовленные свойства
     */
    private function prepareDemoProperties(array $data, array $config, int $iblockId): array
    {
        $properties = $data['properties'] ?? [];
        $prepared = [];

        foreach ($properties as $propCode => $value) {
            // Проверяем есть ли свойство в конфиге
            if (!isset($config['properties'][$propCode])) {
                $prepared[$propCode] = $value;
                continue;
            }

            // Используем PropertyValueConverter для преобразования
            $propertyConfig = $config['properties'][$propCode];
            $convertedValue = $this->valueConverter->convert(
                $value,
                $propertyConfig,
                $iblockId,
                $propCode
            );

            if ($convertedValue !== null) {
                $prepared[$propCode] = $convertedValue;
            }
        }

        return $prepared;
    }

    /**
     * Слить глобальные настройки с локальными
     */
    private function mergeWithGlobalConfig(array $localConfig): array
    {
        $merged = $this->globalConfig;

        foreach ($localConfig as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = array_merge($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Зарегистрировать инфоблок из массива конфигурации
     * Используется для тестирования и программной регистрации
     *
     * @param array $config Конфигурация инфоблока
     * @return void
     */
    public function registerIblockFromConfig(array $config): void
    {
        // Применяем глобальные настройки
        $config = $this->mergeWithGlobalConfig($config);

        $code = $config['iblock']['code'] ?? null;
        if (!$code) {
            throw new \InvalidArgumentException('IBlock code is required in config');
        }

        $typeId = $config['iblock']['type'] ?? null;
        if (!$typeId) {
            throw new \InvalidArgumentException('IBlock type is required in config');
        }

        // Убедиться что тип блока существует (загрузить и зарегистрировать если нужно)
        $this->ensureIblockTypeExists($typeId);

        // Создаем builder если его нет
        if (!isset($this->builder)) {
            $needSync = $this->globalConfig['need_sync'] ?? true;
            $this->builder = $this->builderFactory->create($needSync);
        }

        // Регистрируем
        $this->registerIblock($code, $config);
    }

    /**
     * Убедиться что тип инфоблока существует
     * Загружает и регистрирует тип из конфигурации если нужно
     */
    private function ensureIblockTypeExists(string $typeId): void
    {
        // Проверяем существует ли тип
        $existing = TypeTable::getById($typeId)->fetch();
        if ($existing) {
            return; // Тип уже существует
        }

        // Загружаем конфигурацию типа
        $typeConfigPath = $this->configDir . "/iblock_types/{$typeId}.php";
        if (!file_exists($typeConfigPath)) {
            // Если тип не найден в конфигах, создадим стандартный тип
            $typeConfig = [
                'id' => $typeId,
                'sections' => 'Y',
                'lang' => [
                    'ru' => [
                        'NAME' => ucfirst($typeId),
                        'SECTION_NAME' => 'Sections',
                        'ELEMENT_NAME' => 'Element',
                    ],
                ],
            ];
        } else {
            $typeConfig = require $typeConfigPath;
            if (!is_array($typeConfig)) {
                return;
            }
        }

        // Регистрируем тип
        $this->typeManager->syncTypes([$typeId => $typeConfig], false, true);
    }

    /**
     * Установить глобальную конфигурацию
     * Используется для тестирования
     */
    public function setGlobalConfig(array $globalConfig): void
    {
        $this->globalConfig = $globalConfig;

        // Пересоздаём builder с новым needSync
        $needSync = $globalConfig['need_sync'] ?? true;
        $this->builder = $this->builderFactory->create($needSync);
    }

    /**
     * Получить конфигурацию инфоблока по коду
     */
    public function getConfig(string $code): ?array
    {
        return $this->configs[$code] ?? null;
    }

    /**
     * Получить менеджер для работы с элементами инфоблока
     */
    public function getManager(string $code): ?IBlockConfigManager
    {
        $iblockId = $this->getIblockIdByCode($code);
        if (!$iblockId) {
            return null;
        }

        return new IBlockConfigManager($iblockId, $this->elementManager);
    }
}
