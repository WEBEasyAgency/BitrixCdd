<?php

namespace BitrixCdd\Infrastructure;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;

/**
 * ORM DataManager для таблицы cdd_iblock_versions
 */
class VersionTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'cdd_iblock_versions';
    }

    public static function getMap(): array
    {
        return [
            new Fields\IntegerField('id', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new Fields\StringField('iblock_code', [
                'required' => true,
                'size' => 255,
            ]),
            new Fields\StringField('version', [
                'required' => true,
                'size' => 50,
            ]),
            new Fields\DatetimeField('synced_at', [
                'required' => true,
            ]),
            new Fields\IntegerField('demo_synced', [
                'default_value' => 0,
            ]),
        ];
    }
}
