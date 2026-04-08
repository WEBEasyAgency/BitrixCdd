<?php

namespace BitrixCdd\Infrastructure;

use Bitrix\Main\Loader;
use Bitrix\Iblock\PropertyEnumerationTable;
use BitrixCdd\Services\IBlockConfigService;

/**
 * Извлечение и трансформация данных элементов инфоблока согласно конфигурации
 *
 * Читает описание доступных полей из конфигурации инфоблока и преобразует
 * данные элементов в формат, пригодный для использования в компонентах
 */
class ElementDataExtractor
{
    private IBlockConfigService $configService;

    public function __construct(IBlockConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Получить элементы инфоблока с трансформацией данных
     *
     * @param string $iblockCode Код инфоблока
     * @param array $filter Фильтр для GetList
     * @param array $params Параметры (limit, order и т.д.)
     * @return array Массив трансформированных элементов
     */
    public function getElements(string $iblockCode, array $filter = [], array $params = []): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        // Получаем менеджер инфоблока
        $manager = $this->configService->getManager($iblockCode);
        if (!$manager) {
            return [];
        }

        // Получаем конфигурацию инфоблока
        $config = $this->configService->getConfig($iblockCode);
        if (!$config || !isset($config['fields'])) {
            return [];
        }

        $fieldsConfig = $config['fields'];

        // Подготавливаем список полей для select
        $select = ['ID', 'CODE', 'NAME', 'SORT', 'ACTIVE'];

        // Добавляем только стандартные поля
        foreach ($fieldsConfig as $fieldName => $fieldConfig) {
            if ($fieldConfig['type'] === 'standard') {
                $select[] = $fieldName;
            }
        }

        $select[] = 'PROPERTY_*';

        // Получаем элементы через manager
        $elements = $manager->getElements(array_merge(
            ['filter' => $filter, 'select' => $select],
            $params
        ));

        // Трансформируем каждый элемент
        $result = [];
        foreach ($elements as $element) {
            $result[] = $this->transformElement($element, $fieldsConfig);
        }

        return $result;
    }

    /**
     * Трансформировать один элемент согласно конфигурации полей
     *
     * @param array $element Данные элемента
     * @param array $fieldsConfig Конфигурация полей
     * @return array Трансформированные данные
     */
    private function transformElement(array $element, array $fieldsConfig): array
    {
        if (!isset($element['fields'])) {
            return [];
        }

        $fields = $element['fields'];
        $result = [];

        $elementId = isset($fields['ID']) ? (int)$fields['ID'] : 0;
        $iblockId = isset($fields['IBLOCK_ID']) ? (int)$fields['IBLOCK_ID'] : 0;

        foreach ($fieldsConfig as $fieldName => $fieldConfig) {
            $fieldType = $fieldConfig['type'] ?? null;

            if ($fieldType === 'standard') {
                $rawKey = '~' . $fieldName;
                $value = isset($fields[$rawKey]) ? $fields[$rawKey] : ($fields[$fieldName] ?? null);

                if (in_array($fieldName, ['PREVIEW_PICTURE', 'DETAIL_PICTURE']) && $value) {
                    $value = \CFile::GetPath($value);
                }

                $result[$fieldName] = $value;
            } elseif ($fieldType === 'property') {
                $propertyType = $fieldConfig['property_type'] ?? 'string';
                $result[$fieldName] = $this->transformProperty(
                    $elementId,
                    $iblockId,
                    $fieldName,
                    $propertyType
                );
            }
        }

        if (!isset($result['ACTIVE'])) {
            $result['ACTIVE'] = $fields['ACTIVE'] ?? 'Y';
        }

        return $result;
    }

    /**
     * Преобразовать значение свойства согласно его типу
     *
     * @param int $elementId ID элемента
     * @param int $iblockId ID инфоблока
     * @param string $propertyCode Код свойства
     * @param string $propertyType Тип свойства (string, file, text, enum)
     * @return mixed Преобразованное значение
     */
    private function transformProperty(int $elementId, int $iblockId, string $propertyCode, string $propertyType)
    {
        if (!$elementId || !$iblockId) {
            return $this->getEmptyValue($propertyType);
        }

        // CIBlockElement::GetProperty не имеет D7 аналога для получения свойств элемента
        $propRes = \CIBlockElement::GetProperty($iblockId, $elementId, [], ['CODE' => $propertyCode]);

        if (!$propRes) {
            return $this->getEmptyValue($propertyType);
        }

        $values = [];
        $enumValues = [];
        $isMultiple = false;

        while ($prop = $propRes->Fetch()) {
            if (!isset($isMultipleDefined)) {
                $isMultiple = ($prop['MULTIPLE'] === 'Y');
                $isMultipleDefined = true;
            }

            if ($prop['VALUE']) {
                $values[] = $prop['VALUE'];
                if ($propertyType === 'enum' && !empty($prop['VALUE_ENUM'])) {
                    $enumValues[] = $prop['VALUE_ENUM'];
                }
            }
        }

        if (empty($values)) {
            // Для одиночных файловых свойств возвращаем пустую строку, а не массив
            if (($propertyType === 'file' || $propertyType === 'file_info') && !$isMultiple) {
                return $propertyType === 'file_info' ? null : '';
            }
            return $this->getEmptyValue($propertyType);
        }

        switch ($propertyType) {
            case 'file_info':
                if ($isMultiple) {
                    return $this->transformMultipleFileInfos($values);
                }
                if (!empty($values[0])) {
                    $fileId = is_array($values[0]) ? ($values[0]['ID'] ?? null) : $values[0];
                    return $fileId ? $this->buildFileInfo($fileId) : null;
                }
                return null;

            case 'file':
                if ($isMultiple) {
                    return $this->transformMultipleFiles($values);
                }
                if (!empty($values[0])) {
                    $fileId = is_array($values[0]) ? ($values[0]['ID'] ?? null) : $values[0];
                    return $fileId ? \CFile::GetPath($fileId) : null;
                }
                return null;

            case 'text':
                if ($isMultiple) {
                    $textValues = [];
                    foreach ($values as $value) {
                        if (is_array($value) && isset($value['TEXT'])) {
                            $textValues[] = $value['TEXT'];
                        } else {
                            $textValues[] = (string)$value;
                        }
                    }
                    return $textValues;
                }
                $value = $values[0];
                if (is_array($value) && isset($value['TEXT'])) {
                    return $value['TEXT'];
                }
                return (string)$value;

            case 'enum':
                if ($isMultiple) {
                    return !empty($enumValues) ? $enumValues : [];
                }
                return !empty($enumValues[0]) ? $enumValues[0] : null;

            case 'element':
                if ($isMultiple) {
                    return array_map('intval', $values);
                }
                return !empty($values[0]) ? (int)$values[0] : null;

            case 'string':
            default:
                if ($isMultiple) {
                    return array_map('strval', $values);
                }
                return (string)$values[0];
        }
    }

    /**
     * Получить текстовое значение enum свойства
     *
     * @param int $propertyId ID свойства
     * @param mixed $enumId ID enum значения
     * @return string Текстовое значение enum
     */
    private function getEnumValue(int $propertyId, $enumId): string
    {
        $result = PropertyEnumerationTable::getList([
            'filter' => ['=PROPERTY_ID' => $propertyId, '=ID' => (int)$enumId],
            'select' => ['VALUE'],
            'limit' => 1,
        ]);

        if ($row = $result->fetch()) {
            return $row['VALUE'];
        }

        return '';
    }

    /**
     * Преобразовать массив ID файлов в массив URL путей
     *
     * @param array $fileIds Массив ID файлов
     * @return array Массив URL путей файлов
     */
    private function transformMultipleFiles(array $fileIds): array
    {
        $filePaths = [];

        foreach ($fileIds as $fileId) {
            if (!empty($fileId)) {
                $id = is_array($fileId) ? ($fileId['ID'] ?? null) : $fileId;
                if ($id) {
                    $filePath = \CFile::GetPath($id);
                    if ($filePath) {
                        $filePaths[] = $filePath;
                    }
                }
            }
        }

        return $filePaths;
    }

    /**
     * Получить полную информацию о файле по его ID
     *
     * @param int|string $fileId ID файла в b_file
     * @return array{SRC: string, SIZE: int, FILE_SIZE: string, ORIGINAL_NAME: string}|null
     */
    private function buildFileInfo($fileId): ?array
    {
        $fileArray = \CFile::GetFileArray($fileId);
        if (!$fileArray) {
            return null;
        }

        $ext = strtoupper(pathinfo($fileArray['ORIGINAL_NAME'] ?? $fileArray['FILE_NAME'] ?? '', PATHINFO_EXTENSION));
        $size = (int)($fileArray['FILE_SIZE'] ?? 0);

        return [
            'SRC' => $fileArray['SRC'],
            'SIZE' => $size,
            'FILE_SIZE' => ($ext ? $ext . ', ' : '') . self::formatBytes($size),
            'ORIGINAL_NAME' => $fileArray['ORIGINAL_NAME'] ?? $fileArray['FILE_NAME'] ?? '',
        ];
    }

    /**
     * Преобразовать массив ID файлов в массив с полной информацией
     *
     * @param array $fileIds Массив ID файлов
     * @return array Массив информации о файлах
     */
    private function transformMultipleFileInfos(array $fileIds): array
    {
        $result = [];

        foreach ($fileIds as $fileId) {
            if (!empty($fileId)) {
                $id = is_array($fileId) ? ($fileId['ID'] ?? null) : $fileId;
                if ($id) {
                    $info = $this->buildFileInfo($id);
                    if ($info) {
                        $result[] = $info;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Форматировать размер в байтах в человекочитаемый вид
     */
    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Получить пустое значение для типа свойства
     *
     * @param string $propertyType Тип свойства
     * @return mixed Пустое значение
     */
    private function getEmptyValue(string $propertyType)
    {
        switch ($propertyType) {
            case 'file':
            case 'element':
                return [];
            case 'file_info':
            case 'enum':
                return null;
            case 'text':
            case 'string':
            default:
                return '';
        }
    }
}
