<?php
/**
 * Минимальный конфиг инфоблока
 *
 * Демонстрирует работу умолчаний:
 * - version не указан (по умолчанию '1.0')
 * - sync_mode не указан (по умолчанию 'soft')
 * - type свойств не указан (по умолчанию 'S')
 * - fields не задан (автогенерация из properties)
 * - property_type не задан (автовывод из type свойства)
 * - values в нижнем регистре, плоский список строк
 */
return [
    'iblock' => [
        'code' => 'cdd_minimal',
        'type' => 'content',
        'name' => 'CDD: Минимальный пример',
    ],

    'properties' => [
        'SUBTITLE' => [
            'name' => 'Подзаголовок',
            // type не указан => 'S'
        ],
        'STATUS' => [
            'name' => 'Статус',
            'type' => 'L',
            'values' => ['Черновик', 'Опубликовано', 'Архив'],
        ],
        'IMAGE' => [
            'name' => 'Изображение',
            'type' => 'F',
        ],
    ],

    // fields не задан => автогенерация:
    // SUBTITLE -> property, property_type: string
    // STATUS   -> property, property_type: enum
    // IMAGE    -> property, property_type: file

    'demo_data' => [
        [
            'code' => 'demo-1',
            'name' => 'Демо-элемент',
            'preview_text' => 'Создан из минимального конфига',
            'properties' => [
                'SUBTITLE' => 'Подзаголовок элемента',
                'STATUS' => 'Опубликовано',
            ],
        ],
    ],
];
