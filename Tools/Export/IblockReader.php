<?php

namespace BitrixCdd\Tools\Export;

use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\PropertyEnumerationTable;

/**
 * Чтение инфоблоков и их свойств из БД
 */
class IblockReader
{
    /** [code => id] */
    private array $iblockIdCache = [];

    /** [iblockId => [code => propConfig]] */
    private array $propertyCache = [];

    /**
     * Прочитать инфоблоки с свойствами
     * @return array [code => config, ...]
     */
    public function read(array $typeFilter = []): array
    {
        $iblocks = [];

        $filter = ['!=CODE' => ''];
        if (!empty($typeFilter)) {
            $filter['=IBLOCK_TYPE_ID'] = $typeFilter;
        }

        $result = IblockTable::getList([
            'filter' => $filter,
            'select' => ['ID', 'CODE', 'NAME', 'IBLOCK_TYPE_ID', 'SORT', 'LID'],
            'order' => ['SORT' => 'ASC', 'NAME' => 'ASC'],
        ]);

        while ($iblock = $result->fetch()) {
            $code = $iblock['CODE'];
            if (empty($code)) continue;

            $iblockId = (int)$iblock['ID'];
            $this->iblockIdCache[$code] = $iblockId;

            $config = [
                'version' => '1.0',
                'sync_mode' => 'off',
                'iblock' => [
                    'code' => $code,
                    'type' => $iblock['IBLOCK_TYPE_ID'],
                    'name' => $iblock['NAME'],
                ],
            ];

            if ((int)$iblock['SORT'] !== 500) {
                $config['iblock']['sort'] = (int)$iblock['SORT'];
            }

            $properties = $this->readProperties($iblockId);
            $this->propertyCache[$iblockId] = $properties;

            if (!empty($properties)) {
                $config['properties'] = $properties;
            }

            $iblocks[$code] = $config;
        }

        return $iblocks;
    }

    /**
     * Список инфоблоков для UI (код, имя, тип, количество элементов)
     */
    public function getList(array $typeFilter = []): array
    {
        $list = [];

        $filter = ['!=CODE' => ''];
        if (!empty($typeFilter)) {
            $filter['=IBLOCK_TYPE_ID'] = $typeFilter;
        }

        $result = IblockTable::getList([
            'filter' => $filter,
            'select' => ['ID', 'CODE', 'NAME', 'IBLOCK_TYPE_ID'],
            'order' => ['IBLOCK_TYPE_ID' => 'ASC', 'SORT' => 'ASC'],
        ]);

        while ($iblock = $result->fetch()) {
            $code = $iblock['CODE'];
            if (empty($code)) continue;

            $list[] = [
                'code' => $code,
                'name' => $iblock['NAME'],
                'type' => $iblock['IBLOCK_TYPE_ID'],
                'count' => \Bitrix\Iblock\ElementTable::getCount(['=IBLOCK_ID' => (int)$iblock['ID']]),
            ];
        }

        return $list;
    }

    public function getIdByCode(string $code): ?int
    {
        if (isset($this->iblockIdCache[$code])) {
            return $this->iblockIdCache[$code];
        }

        $result = IblockTable::getList([
            'filter' => ['=CODE' => $code],
            'select' => ['ID'],
            'limit' => 1,
        ]);

        if ($row = $result->fetch()) {
            $this->iblockIdCache[$code] = (int)$row['ID'];
            return (int)$row['ID'];
        }

        return null;
    }

    public function getPropertyCache(int $iblockId): array
    {
        return $this->propertyCache[$iblockId] ?? [];
    }

    // ── Private ────────────────────────────────────────────────

    private function readProperties(int $iblockId): array
    {
        $properties = [];

        $result = PropertyTable::getList([
            'filter' => ['=IBLOCK_ID' => $iblockId, '!=CODE' => ''],
            'select' => [
                'ID', 'CODE', 'NAME', 'PROPERTY_TYPE', 'SORT',
                'MULTIPLE', 'IS_REQUIRED', 'USER_TYPE', 'FILE_TYPE',
                'LINK_IBLOCK_ID', 'USER_TYPE_SETTINGS_LIST',
            ],
            'order' => ['SORT' => 'ASC'],
        ]);

        while ($prop = $result->fetch()) {
            $code = $prop['CODE'];
            if (empty($code)) continue;

            $config = ['name' => $prop['NAME']];

            $propType = $prop['PROPERTY_TYPE'];
            if ($propType !== 'S') {
                $config['type'] = $propType;
            }

            if ((int)$prop['SORT'] !== 500 && (int)$prop['SORT'] !== 100) {
                $config['sort'] = (int)$prop['SORT'];
            }

            if ($prop['MULTIPLE'] === 'Y') $config['multiple'] = true;
            if ($prop['IS_REQUIRED'] === 'Y') $config['required'] = true;
            if (!empty($prop['USER_TYPE'])) $config['user_type'] = $prop['USER_TYPE'];
            if (!empty($prop['FILE_TYPE'])) $config['file_type'] = $prop['FILE_TYPE'];

            if (!empty($prop['LINK_IBLOCK_ID']) && in_array($propType, ['E', 'G'])) {
                $linkedCode = $this->getCodeById((int)$prop['LINK_IBLOCK_ID']);
                if ($linkedCode) {
                    $config['link_iblock_id'] = $linkedCode;
                }
            }

            if ($propType === 'L') {
                $values = $this->readEnumValues((int)$prop['ID']);
                if (!empty($values)) {
                    $config['values'] = $values;
                }
            }

            $config['_bitrix_type'] = $propType;
            $properties[$code] = $config;
        }

        return $properties;
    }

    private function readEnumValues(int $propertyId): array
    {
        $canUseShortFormat = true;
        $rawValues = [];

        $result = PropertyEnumerationTable::getList([
            'filter' => ['=PROPERTY_ID' => $propertyId],
            'select' => ['VALUE', 'SORT', 'DEF', 'XML_ID'],
            'order' => ['SORT' => 'ASC'],
        ]);

        while ($enum = $result->fetch()) {
            $rawValues[] = $enum;
            if ($enum['DEF'] === 'Y' || !empty($enum['XML_ID'])) {
                $canUseShortFormat = false;
            }
        }

        if ($canUseShortFormat) {
            return array_column($rawValues, 'VALUE');
        }

        $values = [];
        foreach ($rawValues as $enum) {
            $entry = ['VALUE' => $enum['VALUE'], 'SORT' => (int)$enum['SORT']];
            if ($enum['DEF'] === 'Y') $entry['DEF'] = 'Y';
            if (!empty($enum['XML_ID'])) $entry['XML_ID'] = $enum['XML_ID'];
            $values[] = $entry;
        }

        return $values;
    }

    private function getCodeById(int $id): ?string
    {
        $result = IblockTable::getList([
            'filter' => ['=ID' => $id],
            'select' => ['CODE'],
            'limit' => 1,
        ]);

        $row = $result->fetch();
        return $row ? ($row['CODE'] ?: null) : null;
    }
}
