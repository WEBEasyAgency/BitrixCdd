<?php
/**
 * Пример конфига с include_standard_fields
 *
 * Демонстрирует автогенерацию fields с подключением стандартных полей
 */
return [
    'iblock' => [
        'code' => 'cdd_std_fields',
        'type' => 'content',
        'name' => 'CDD: Стандартные поля',
    ],

    // true -- полный набор: NAME, SORT, PREVIEW_TEXT, PREVIEW_PICTURE, DETAIL_TEXT, DETAIL_PICTURE
    // массив -- только перечисленные: ['NAME', 'PREVIEW_PICTURE']
    // false (по умолчанию) -- только свойства из properties
    'include_standard_fields' => ['NAME', 'PREVIEW_TEXT', 'PREVIEW_PICTURE'],

    'properties' => [
        'AUTHOR' => [
            'name' => 'Автор',
            // type по умолчанию 'S'
        ],
        'CATEGORY' => [
            'name' => 'Категория',
            'type' => 'L',
            'values' => ['Новости', 'Статьи', 'Обзоры'],
        ],
        'COVER' => [
            'name' => 'Обложка',
            'type' => 'F',
            'file_type' => 'jpg,png,webp',
        ],
        'BODY' => [
            'name' => 'Содержание',
            'user_type' => 'HTML',
            // type по умолчанию 'S', property_type автоматически 'text'
        ],
    ],

    // fields не задан, будет сгенерирован автоматически:
    // NAME            -> standard
    // PREVIEW_TEXT    -> standard
    // PREVIEW_PICTURE -> standard
    // AUTHOR          -> property, property_type: string
    // CATEGORY        -> property, property_type: enum
    // COVER           -> property, property_type: file
    // BODY            -> property, property_type: text
];
