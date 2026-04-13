<?php

namespace BitrixCdd\Tools\Export;

/**
 * Чтение элементов инфобло��а для экспорта в demo_data
 */
class DemoDataReader
{
    private AssetCollector $assets;

    public function __construct(AssetCollector $assets)
    {
        $this->assets = $assets;
    }

    /**
     * @return array Массив demo_data элементов
     */
    public function read(int $iblockId, string $iblockCode, array $propertiesConfig): array
    {
        $demoData = [];

        $res = \CIBlockElement::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            ['IBLOCK_ID' => $iblockId],
            false,
            false,
            ['ID', 'CODE', 'NAME', 'ACTIVE', 'SORT', 'PREVIEW_TEXT', 'DETAIL_TEXT',
             'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'XML_ID']
        );

        while ($element = $res->GetNextElement()) {
            $fields = $element->GetFields();
            $props = $element->GetProperties();

            $entry = $this->buildEntry($fields, $iblockCode);
            $entryProps = $this->buildProperties($props, $propertiesConfig, $iblockCode);

            if (!empty($entryProps)) {
                $entry['properties'] = $entryProps;
            }

            $demoData[] = $entry;
        }

        return $demoData;
    }

    private function buildEntry(array $fields, string $iblockCode): array
    {
        $code = $fields['CODE'] ?: ('element-' . $fields['ID']);

        $entry = [
            'code' => $code,
            'name' => $fields['NAME'],
        ];

        if ($fields['ACTIVE'] !== 'Y') {
            $entry['active'] = $fields['ACTIVE'];
        }

        if ((int)$fields['SORT'] !== 500) {
            $entry['sort'] = (int)$fields['SORT'];
        }

        if (!empty($fields['PREVIEW_TEXT'])) {
            $entry['preview_text'] = $fields['PREVIEW_TEXT'];
        }

        if (!empty($fields['DETAIL_TEXT'])) {
            $entry['detail_text'] = $fields['DETAIL_TEXT'];
        }

        if (!empty($fields['PREVIEW_PICTURE'])) {
            $path = $this->assets->collect((int)$fields['PREVIEW_PICTURE'], $iblockCode);
            if ($path) $entry['preview_picture'] = $path;
        }

        if (!empty($fields['DETAIL_PICTURE'])) {
            $path = $this->assets->collect((int)$fields['DETAIL_PICTURE'], $iblockCode);
            if ($path) $entry['detail_picture'] = $path;
        }

        return $entry;
    }

    private function buildProperties(array $props, array $config, string $iblockCode): array
    {
        $result = [];

        foreach ($props as $propCode => $prop) {
            if (empty($prop['VALUE'])) continue;

            $bitrixType = $config[$propCode]['_bitrix_type'] ?? $prop['PROPERTY_TYPE'] ?? 'S';
            $isMultiple = $prop['MULTIPLE'] === 'Y';
            $userType = $prop['USER_TYPE'] ?? '';

            $value = $this->convertPropertyValue($prop, $bitrixType, $isMultiple, $userType, $iblockCode);

            if ($value !== null) {
                $result[$propCode] = $value;
            }
        }

        return $result;
    }

    private function convertPropertyValue(array $prop, string $type, bool $isMultiple, string $userType, string $iblockCode): mixed
    {
        return match ($type) {
            'L' => $this->convertEnum($prop, $isMultiple),
            'F' => $this->convertFile($prop, $isMultiple, $iblockCode),
            'E' => $this->convertElementLink($prop, $isMultiple),
            'N' => $this->convertNumber($prop, $isMultiple),
            'S' => $this->convertString($prop, $isMultiple, $userType),
            default => $prop['VALUE'] !== '' ? $prop['VALUE'] : null,
        };
    }

    private function convertEnum(array $prop, bool $isMultiple): mixed
    {
        if ($isMultiple) {
            $values = array_filter((array)$prop['VALUE_ENUM'], fn($v) => !empty($v));
            return !empty($values) ? array_values($values) : null;
        }
        return !empty($prop['VALUE_ENUM']) ? $prop['VALUE_ENUM'] : null;
    }

    private function convertFile(array $prop, bool $isMultiple, string $iblockCode): mixed
    {
        if ($isMultiple) {
            $paths = [];
            foreach ((array)$prop['VALUE'] as $fileId) {
                $path = $this->assets->collect((int)$fileId, $iblockCode);
                if ($path) $paths[] = $path;
            }
            return !empty($paths) ? $paths : null;
        }

        return $this->assets->collect((int)$prop['VALUE'], $iblockCode);
    }

    private function convertElementLink(array $prop, bool $isMultiple): mixed
    {
        if ($isMultiple) {
            $values = [];
            foreach ((array)$prop['VALUE'] as $v) {
                $code = $this->getElementCodeById((int)$v);
                $values[] = $code ?: (int)$v;
            }
            return !empty($values) ? $values : null;
        }

        $code = $this->getElementCodeById((int)$prop['VALUE']);
        return $code ?: (int)$prop['VALUE'];
    }

    private function convertNumber(array $prop, bool $isMultiple): mixed
    {
        $cast = fn($v) => (float)$v === floor((float)$v) ? (int)$v : (float)$v;

        if ($isMultiple) {
            $values = array_map($cast, (array)$prop['VALUE']);
            return !empty($values) ? $values : null;
        }

        return $cast($prop['VALUE']);
    }

    private function convertString(array $prop, bool $isMultiple, string $userType): mixed
    {
        if ($userType === 'HTML') {
            $value = $prop['~VALUE'] ?? $prop['VALUE'];
            if (is_array($value) && isset($value['TEXT'])) return $value['TEXT'];
            return is_string($value) && $value !== '' ? $value : null;
        }

        if ($userType === 'Date' || $userType === 'DateTime') {
            $value = $prop['VALUE'];
            if (empty($value)) return null;

            if ($userType === 'DateTime') {
                $dt = \DateTime::createFromFormat('d.m.Y H:i:s', $value);
                return $dt ? $dt->format('Y-m-d H:i:s') : $value;
            }
            $dt = \DateTime::createFromFormat('d.m.Y', $value);
            return $dt ? $dt->format('Y-m-d') : $value;
        }

        if ($isMultiple) {
            $values = array_filter((array)$prop['VALUE'], fn($v) => $v !== '' && $v !== null);
            return !empty($values) ? array_values($values) : null;
        }

        return ($prop['VALUE'] !== '' && $prop['VALUE'] !== null) ? $prop['VALUE'] : null;
    }

    private function getElementCodeById(int $elementId): ?string
    {
        $result = \Bitrix\Iblock\ElementTable::getList([
            'filter' => ['=ID' => $elementId],
            'select' => ['CODE'],
            'limit' => 1,
        ]);

        $row = $result->fetch();
        return $row ? ($row['CODE'] ?: null) : null;
    }
}
