<?php

namespace BitrixCdd\Services;

use Bitrix\Main\Loader;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Iblock\ElementTable;

/**
 * Конвертер значений свойств для demo_data
 *
 * Преобразует значения из конфигурации в формат, понятный Bitrix:
 * - Enum: текстовые значения → ID enum
 * - File: пути к файлам → загруженные файлы
 * - Date/DateTime: строки → форматированные даты
 * - Element/Section: ID или коды → ID элементов/разделов
 */
class PropertyValueConverter
{
    public function __construct()
    {
        Loader::includeModule('iblock');
    }

    /**
     * Конвертировать значение свойства согласно его типу
     *
     * @param mixed $value Значение из demo_data
     * @param array $propertyConfig Конфигурация свойства
     * @param int $iblockId ID инфоблока (для enum lookup)
     * @param string $propertyCode Код свойства
     * @return mixed Преобразованное значение
     */
    public function convert($value, array $propertyConfig, int $iblockId, string $propertyCode)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $type = $propertyConfig['type'] ?? 'S';
        $isMultiple = $propertyConfig['multiple'] ?? false;

        return match ($type) {
            'L' => $this->convertEnum($value, $iblockId, $propertyCode, $isMultiple),
            'F' => $this->convertFile($value, $isMultiple),
            'E' => $this->convertElement($value, $isMultiple),
            'G' => $this->convertSection($value, $isMultiple),
            'S' => $this->convertString($value, $propertyConfig, $isMultiple),
            'N' => $this->convertNumber($value),
            default => $value,
        };
    }

    /**
     * Конвертировать enum значение (текст → ID)
     */
    private function convertEnum($value, int $iblockId, string $propertyCode, bool $isMultiple)
    {
        if (!$isMultiple) {
            return $this->getEnumIdByValue($iblockId, $propertyCode, $value);
        }

        // Множественное enum
        $enumIds = [];
        foreach ((array)$value as $enumValue) {
            $enumId = $this->getEnumIdByValue($iblockId, $propertyCode, $enumValue);
            if ($enumId) {
                $enumIds[] = $enumId;
            }
        }
        return $enumIds;
    }

    /**
     * Получить ID enum значения по его текстовому представлению
     */
    private function getEnumIdByValue(int $iblockId, string $propertyCode, string $value): ?int
    {
        // Получаем ID свойства
        $propResult = PropertyTable::getList([
            'filter' => ['=IBLOCK_ID' => $iblockId, '=CODE' => $propertyCode],
            'select' => ['ID'],
            'limit' => 1,
        ]);
        $property = $propResult->fetch();
        if (!$property) {
            return null;
        }

        // Получаем ID enum значения
        $enumResult = PropertyEnumerationTable::getList([
            'filter' => ['=PROPERTY_ID' => $property['ID'], '=VALUE' => $value],
            'select' => ['ID'],
            'limit' => 1,
        ]);
        $enum = $enumResult->fetch();

        return $enum ? (int)$enum['ID'] : null;
    }

    /**
     * Конвертировать файл (путь → загруженный файл)
     */
    private function convertFile($value, bool $isMultiple)
    {
        if (!$isMultiple) {
            return $this->registerFile($value);
        }

        // Множественные файлы
        $fileIds = [];
        foreach ((array)$value as $filePath) {
            $fileId = $this->registerFile($filePath);
            if ($fileId) {
                $fileIds[] = $fileId;
            }
        }
        return $fileIds;
    }

    /**
     * Зарегистрировать файл в Bitrix
     */
    private function registerFile(string $filePath): ?int
    {
        $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $filePath;

        if (!file_exists($absolutePath)) {
            return null;
        }

        // CFile не имеет D7 аналога
        $fileArray = \CFile::MakeFileArray($absolutePath);
        if (!$fileArray) {
            return null;
        }

        $fileId = \CFile::SaveFile($fileArray, 'iblock');
        return $fileId ? (int)$fileId : null;
    }

    /**
     * Конвертировать привязку к элементу (поддержка CODE и ID)
     */
    private function convertElement($value, bool $isMultiple)
    {
        if (!$isMultiple) {
            if (is_string($value) && !is_numeric($value)) {
                return $this->getElementIdByCode($value);
            }
            return (int)$value;
        }

        $ids = [];
        foreach ((array)$value as $val) {
            if (is_string($val) && !is_numeric($val)) {
                $id = $this->getElementIdByCode($val);
                if ($id) {
                    $ids[] = $id;
                }
            } else {
                $ids[] = (int)$val;
            }
        }
        return $ids;
    }

    /**
     * Получить ID элемента по его символьному коду
     */
    private function getElementIdByCode(string $code): ?int
    {
        $result = ElementTable::getList([
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
     * Конвертировать привязку к разделу
     */
    private function convertSection($value, bool $isMultiple)
    {
        if (!$isMultiple) {
            return (int)$value;
        }

        return array_map('intval', (array)$value);
    }

    /**
     * Конвертировать строку (с учетом user_type: Date, DateTime, HTML)
     */
    private function convertString($value, array $propertyConfig, bool $isMultiple)
    {
        $userType = $propertyConfig['user_type'] ?? '';

        if ($userType === 'Date' || $userType === 'DateTime') {
            return $this->convertDate($value, $userType);
        }

        if ($userType === 'HTML') {
            if (!$isMultiple) {
                return ['VALUE' => ['TEXT' => $value, 'TYPE' => 'html']];
            }
            $htmlValues = [];
            foreach ((array)$value as $htmlValue) {
                $htmlValues[] = ['VALUE' => ['TEXT' => $htmlValue, 'TYPE' => 'html']];
            }
            return $htmlValues;
        }

        if (!$isMultiple) {
            return $value;
        }

        return (array)$value;
    }

    /**
     * Конвертировать дату
     */
    private function convertDate(string $value, string $userType): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        if ($userType === 'DateTime') {
            return date('d.m.Y H:i:s', $timestamp);
        }

        return date('d.m.Y', $timestamp);
    }

    /**
     * Конвертировать число
     */
    private function convertNumber($value): float
    {
        return is_numeric($value) ? (float)$value : 0.0;
    }
}
