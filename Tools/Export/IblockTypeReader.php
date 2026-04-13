<?php

namespace BitrixCdd\Tools\Export;

use Bitrix\Iblock\TypeTable;

/**
 * Чтение типов инфоблоков из БД
 */
class IblockTypeReader
{
    /**
     * @return array [typeId => config, ...]
     */
    public function read(array $typeFilter = []): array
    {
        $types = [];

        $filter = [];
        if (!empty($typeFilter)) {
            $filter['=ID'] = $typeFilter;
        }

        $result = TypeTable::getList([
            'filter' => $filter,
            'select' => ['ID', 'SECTIONS', 'SORT'],
        ]);

        while ($type = $result->fetch()) {
            $typeId = $type['ID'];

            $config = [
                'id' => $typeId,
                'sections' => $type['SECTIONS'] ?? 'Y',
            ];

            $langs = $this->readLangs($typeId);
            if (!empty($langs)) {
                $config['lang'] = $langs;
            }

            $types[$typeId] = $config;
        }

        return $types;
    }

    private function readLangs(string $typeId): array
    {
        $langs = [];

        foreach (['ru', 'en'] as $langId) {
            $data = \CIBlockType::GetByIDLang($typeId, $langId);
            if (!$data || empty($data['NAME'])) continue;

            $entry = [];
            if (!empty($data['NAME'])) $entry['NAME'] = $data['NAME'];
            if (!empty($data['SECTION_NAME'])) $entry['SECTION_NAME'] = $data['SECTION_NAME'];
            if (!empty($data['ELEMENT_NAME'])) $entry['ELEMENT_NAME'] = $data['ELEMENT_NAME'];

            if (!empty($entry)) {
                $langs[$langId] = $entry;
            }
        }

        return $langs;
    }
}
