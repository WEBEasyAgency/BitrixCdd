<?php
/**
 * Полный пример конфигурации инфоблока
 *
 * Демонстрирует:
 * - Все типы свойств (S, N, L, F, E, Date, DateTime, HTML)
 * - Секцию fields для ElementDataExtractor
 * - Правила валидации
 * - Демо-данные с автоматической конвертацией значений
 */
return [
    'version'   => '1.0',
    'sync_mode' => 'soft',       // off | soft | ensure | once | danger
    'priority'  => 100,          // Порядок регистрации (меньше = раньше)

    'iblock' => [
        'code' => 'cdd_full_test',
        'type' => 'test_type',
        'name' => 'CDD: Полный тест свойств',
        'sort' => 10,
    ],

    // Переопределение iblock_fields для этого конкретного инфоблока
    // (мержится с global.php → iblock_fields)
    'iblock_fields' => [
        'CODE' => [
            'IS_REQUIRED' => 'Y',    // Сделать CODE обязательным
        ],
    ],

    // ── Свойства ────────────────────────────────────────────────
    'properties' => [
        // Строка с валидацией длины
        'TITLE' => [
            'name'     => 'Заголовок',
            'type'     => 'S',
            'sort'     => 100,
            'required' => true,
            'validation' => [
                'max_length' => 90,    // Макс. 90 символов (HTML-теги вырезаются перед проверкой)
            ],
        ],
        // Число с валидацией минимума
        'PRICE' => [
            'name'     => 'Цена',
            'type'     => 'N',
            'sort'     => 200,
            'validation' => [
                'min'           => 0,      // Минимальное значение
                'min_inclusive'  => false,  // false = строго больше нуля (>), true = >= (по умолчанию)
            ],
        ],
        // Список (enum)
        'STATUS' => [
            'name'     => 'Статус',
            'type'     => 'L',
            'sort'     => 300,
            'VALUES'   => [
                ['VALUE' => 'Черновик',      'SORT' => 10, 'DEF' => 'Y'],
                ['VALUE' => 'Опубликовано',  'SORT' => 20],
                ['VALUE' => 'Архив',         'SORT' => 30],
            ],
        ],
        // Одиночный файл (обязательный — автоматически RequiredFileRule)
        'COVER' => [
            'name'     => 'Обложка',
            'type'     => 'F',
            'sort'     => 400,
            'required' => true,
            'file_type' => 'jpg,png,webp',
        ],
        // Множественные файлы с ограничением количества
        'GALLERY' => [
            'name'     => 'Галерея',
            'type'     => 'F',
            'multiple' => true,
            'sort'     => 500,
            'validation' => [
                'max_count' => 10,   // Максимум 10 файлов
            ],
        ],
        // Дата
        'DATE_START' => [
            'name'      => 'Дата начала',
            'type'      => 'S',
            'user_type' => 'Date',
            'sort'      => 600,
        ],
        // Дата и время
        'PUBLISHED_AT' => [
            'name'      => 'Дата публикации',
            'type'      => 'S',
            'user_type' => 'DateTime',
            'sort'      => 700,
        ],
        // HTML-редактор
        'BODY' => [
            'name'      => 'Текст',
            'type'      => 'S',
            'user_type' => 'HTML',
            'sort'      => 800,
        ],
        // Привязка к элементу другого инфоблока
        'RELATED' => [
            'name'           => 'Связанный элемент',
            'type'           => 'E',
            'sort'           => 900,
            // 'link_iblock_id' => 'articles', // код инфоблока (не ID!)
        ],
    ],

    // ── Описание полей для ElementDataExtractor ─────────────────
    // Без этой секции extractor вернёт пустой массив!
    'fields' => [
        // Стандартные поля Bitrix
        'NAME'            => ['type' => 'standard'],
        'SORT'            => ['type' => 'standard'],
        'PREVIEW_TEXT'    => ['type' => 'standard'],
        'PREVIEW_PICTURE' => ['type' => 'standard'],     // ID файла → URL автоматически
        'DETAIL_TEXT'     => ['type' => 'standard'],

        // Свойства — property_type определяет трансформацию при чтении
        'TITLE'        => ['type' => 'property', 'property_type' => 'string'],
        'PRICE'        => ['type' => 'property', 'property_type' => 'string'],
        'STATUS'       => ['type' => 'property', 'property_type' => 'enum'],     // enum ID → текст
        'COVER'        => ['type' => 'property', 'property_type' => 'file'],     // file ID → URL
        'GALLERY'      => ['type' => 'property', 'property_type' => 'file'],     // file IDs → URLs
        'DATE_START'   => ['type' => 'property', 'property_type' => 'string'],
        'PUBLISHED_AT' => ['type' => 'property', 'property_type' => 'string'],
        'BODY'         => ['type' => 'property', 'property_type' => 'text'],     // ['TEXT'=>...] → строка
        'RELATED'      => ['type' => 'property', 'property_type' => 'element'],  // оставляет ID

        // Пример file_info — расширенная информация о файле (URL + размер + имя)
        // 'DOCUMENT' => ['type' => 'property', 'property_type' => 'file_info'],
    ],

    // ── Демо-данные ─────────────────────────────────────────────
    'demo_data' => [
        [
            'code'         => 'test-item-1',
            'name'         => 'Элемент со всеми данными',
            'active'       => 'Y',
            'sort'         => 100,
            'preview_text' => 'Анонс тестового элемента',
            'detail_text'  => 'Детальное описание.',
            'properties'   => [
                'TITLE'        => 'Тестовый заголовок',
                'PRICE'        => 1500,
                'STATUS'       => 'Опубликовано',                  // текст, не ID!
                'COVER'        => '/local/src/BitrixCdd/examples/test_file.txt',
                'GALLERY'      => [
                    '/local/src/BitrixCdd/examples/test_file.txt',
                    '/local/src/BitrixCdd/examples/test_file.txt',
                ],
                'DATE_START'   => '2026-01-26',                    // → 26.01.2026
                'PUBLISHED_AT' => '2026-01-26 15:30:00',           // → 26.01.2026 15:30:00
                'BODY'         => '<p>Это <b>HTML</b> контент</p>',
            ],
        ],
    ],
];
