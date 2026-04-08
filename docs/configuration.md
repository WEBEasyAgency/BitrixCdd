# Конфигурация

## Структура конфигов

```
local/config/
├── global.php              # Глобальные настройки (мержатся во все конфиги)
├── iblock_types/           # Типы инфоблоков (один файл = один тип)
│   └── content.php
└── iblocks/                # Инфоблоки (один файл = один инфоблок)
    └── news.php
```

Путь к конфигам можно переопределить:

```php
$app->initialize([
    'config_dir' => $_SERVER['DOCUMENT_ROOT'] . '/custom/config',
]);
```

---

## Глобальный конфиг (global.php)

```php
<?php
return [
    'need_sync' => true,    // Обновлять структуру при изменении версии
    'strict'    => false,   // Удалять лишние свойства/элементы (подробнее ниже)

    // Настройки полей для CIBlock::SetFields (применяются ко ВСЕМ инфоблокам)
    'iblock_fields' => [
        'CODE' => [
            'IS_REQUIRED' => 'N',
            'DEFAULT_VALUE' => [
                'TRANSLITERATION' => 'Y',   // Автогенерация CODE из NAME
                'TRANS_LEN'       => 100,
                'TRANS_CASE'      => 'L',   // Нижний регистр
                'TRANS_SPACE'     => '-',
                'TRANS_OTHER'     => '-',
                'TRANS_EAT'       => 'Y',
                'USE_GOOGLE'      => 'N',
            ],
        ],
    ],
];
```

Всё из `global.php` мержится в каждый конфиг инфоблока как значения по умолчанию. Конфиг конкретного инфоблока может переопределить любое значение.

---

## Тип инфоблока

Инфоблок всегда привязан к типу. Поэтому перед созданием инфоблока нужно убедиться, что его тип существует — либо создать конфиг типа.

Файл `local/config/iblock_types/content.php`:

```php
<?php
return [
    'id'       => 'content',        // Обязательно. Символьный ID типа
    'sections' => 'Y',              // 'Y' | 'N', по умолчанию 'Y'
    'lang'     => [
        'ru' => [
            'NAME'         => 'Контент',
            'SECTION_NAME' => 'Разделы',
            'ELEMENT_NAME' => 'Элементы',
        ],
        'en' => [
            'NAME'         => 'Content',
            'SECTION_NAME' => 'Sections',
            'ELEMENT_NAME' => 'Elements',
        ],
    ],
];
```

Тип создаётся при инициализации. Если тип уже существует и `need_sync: true` — обновляется.

---

## Конфиг инфоблока — полный формат

```php
<?php
return [
    // ── Метаданные ──────────────────────────────────────────────
    'version'   => '1.0',       // Версия конфига (подробнее в versioning.md)
    'need_sync' => true,        // Переопределяет значение из global.php
    'strict'    => false,       // Переопределяет значение из global.php
    'priority'  => 100,         // Порядок регистрации (по умолчанию 500, меньше = раньше)

    // ── Инфоблок ────────────────────────────────────────────────
    'iblock' => [
        'code' => 'news',       // Обязательно. Символьный код
        'type' => 'content',    // Обязательно. ID типа инфоблока
        'name' => 'Новости',    // Обязательно. Название
        'sort' => 100,          // Сортировка в списке инфоблоков (по умолчанию 500)
    ],

    // ── Настройки полей инфоблока (опционально) ─────────────────
    // Мержатся с global.php → iblock_fields. Передаются в CIBlock::SetFields
    'iblock_fields' => [
        'CODE' => [
            'IS_REQUIRED' => 'Y',   // Сделать CODE обязательным для этого инфоблока
        ],
    ],

    // ── Описание полей для ElementDataExtractor (опционально) ───
    'fields' => [
        'NAME'            => ['type' => 'standard'],
        'PREVIEW_TEXT'    => ['type' => 'standard'],
        'PREVIEW_PICTURE' => ['type' => 'standard'],          // ID файла → URL
        'DETAIL_TEXT'     => ['type' => 'standard'],
        'AUTHOR'          => ['type' => 'property', 'property_type' => 'string'],
        'TAGS'            => ['type' => 'property', 'property_type' => 'enum'],
        'IMAGE'           => ['type' => 'property', 'property_type' => 'file'],
        'AVATAR'          => ['type' => 'property', 'property_type' => 'file_info'],
        'LINKED'          => ['type' => 'property', 'property_type' => 'element'],
        'BODY'            => ['type' => 'property', 'property_type' => 'text'],
    ],

    // ── Свойства инфоблока ──────────────────────────────────────
    'properties' => [
        'AUTHOR' => [
            'name'     => 'Автор',            // Обязательно
            'type'     => 'S',                // Обязательно (см. таблицу типов)
            'sort'     => 100,                // По умолчанию 100
            'required' => true,               // По умолчанию false
            'multiple' => false,              // По умолчанию false
            'user_type' => '',                // Для дат, HTML и т.д.
            'settings'  => [],                // USER_TYPE_SETTINGS (передаётся как есть)
            'file_type' => 'jpg,png,gif',     // Только для type=F
            'link_iblock_id' => 'articles',   // Только для type=E|G (код связанного инфоблока)
            'validation' => [...],            // Правила валидации (см. validation.md)
        ],
    ],

    // ── Демо-данные (опционально) ───────────────────────────────
    'demo_data' => [...],   // Подробнее в demo-data.md
];
```

### Типы свойств

| `type` | Описание | Примечания |
|--------|----------|------------|
| `S` | Строка | Базовый тип. С `user_type` становится Date, DateTime, HTML |
| `N` | Число | Целое или дробное |
| `L` | Список (enum) | Требует ключ `VALUES` |
| `F` | Файл | Можно ограничить расширения через `file_type` |
| `E` | Привязка к элементу | Можно указать `link_iblock_id` |
| `G` | Привязка к разделу | Можно указать `link_iblock_id` |
| `T` | Текст | Алиас для `S` (в Bitrix это тоже строка) |

### Свойство типа "Список" (L)

```php
'TAGS' => [
    'name'     => 'Теги',
    'type'     => 'L',
    'multiple' => true,
    'VALUES'   => [
        ['VALUE' => 'Важное',  'SORT' => 10, 'DEF' => 'N'],
        ['VALUE' => 'Черновик', 'SORT' => 20, 'DEF' => 'Y'],  // DEF = значение по умолчанию
    ],
],
```

### Свойства с user_type

```php
// Дата
'DATE_START' => [
    'name'      => 'Дата начала',
    'type'      => 'S',
    'user_type' => 'Date',
],

// Дата и время
'PUBLISHED_AT' => [
    'name'      => 'Дата публикации',
    'type'      => 'S',
    'user_type' => 'DateTime',
],

// HTML-редактор
'BODY' => [
    'name'      => 'Текст',
    'type'      => 'S',
    'user_type' => 'HTML',
],
```

### Конфиг — это обычный PHP

Конфиг загружается через `require`, так что внутри можно использовать любой PHP: циклы, условия, переменные, функции. Главное — в конце `return` с массивом.

Пример — генерация демо-данных циклом:

```php
<?php
$demoData = [];
for ($i = 1; $i <= 5; $i++) {
    $demoData[] = [
        'code' => "slide-{$i}",
        'name' => "Слайд {$i}",
        'sort' => $i * 100,
        'properties' => [
            'BANNER_BG'   => '/local/config/demo_data/banners/bg.webp',
            'BANNER_LINK' => "/promo/slide-{$i}/",
        ],
    ];
}

return [
    'version' => '1.0',
    'need_sync' => true,
    'strict' => true,
    'iblock' => [
        'code' => 'promo_banners',
        'type' => 'content',
        'name' => 'Промо-баннеры',
    ],
    'properties' => [
        'BANNER_BG'   => ['name' => 'Фон',   'type' => 'F'],
        'BANNER_LINK' => ['name' => 'Ссылка', 'type' => 'S'],
    ],
    'demo_data' => $demoData,
];
```

Можно и условия: например, генерировать демо-данные только на dev-окружении:

```php
'demo_data' => getenv('APP_ENV') === 'dev' ? $demoData : [],
```

---

## Программная регистрация инфоблоков

Кроме файловых конфигов, инфоблоки можно создавать программно:

```php
$configService = $app->getService('iblock.config');

$configService->registerIblockFromConfig([
    'iblock' => [
        'code' => 'dynamic_catalog',
        'type' => 'content',
        'name' => 'Динамический каталог',
    ],
    'properties' => [
        'PRICE' => ['name' => 'Цена', 'type' => 'N'],
        'COLOR' => [
            'name' => 'Цвет',
            'type' => 'L',
            'VALUES' => [
                ['VALUE' => 'Красный'],
                ['VALUE' => 'Синий'],
            ],
        ],
    ],
]);
```

Если тип инфоблока не существует, метод попытается найти его конфиг в `iblock_types/{typeId}.php` или создаст тип с дефолтными настройками.
