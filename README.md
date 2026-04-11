# BitrixCdd — Config-Driven Development для Битрикс

Библиотека, которая позволяет описывать инфоблоки, свойства и демо-данные в PHP-конфигах — а не тыкать мышкой в админке. Конфиги лежат в репозитории, версионируются через git, и синхронизируются с БД автоматически.

## Какие проблемы решает

- **Миграции** — структура инфоблоков описана в коде и создаётся одинаково на всех окружениях.
- **Ручная работа в админке** — не нужно создавать инфоблоки, свойства и тестовый контент руками. Описал в конфиге — при первом хите всё появится.
- **Рассинхрон между окружениями** — версионирование конфигов гарантирует, что изменения применятся ровно один раз.
- **Валидация** — позволяет валидировать поля в админке на обязятельность, ограничения по символам и т.д. (часто бывает в ТЗ)
- **Единый источник данных для компонентов** — `ElementDataExtractor` сам конвертирует ID файлов в URL, enum-ы в текст и т.д., не нужно каждый раз прописывать список полей элемента вручную

> ### Про strict: прочитай перед использованием
>
> В конфиге есть флаг `strict: true`. Он делает конфиг **единственным источником правды** — и это значит две вещи:
>
> 1. **Удаляет всё, чего нет в конфиге.** Свойства, которые кто-то добавил руками в админке — удалятся. Элементы, которые создал контент-менеджер — удалятся. Если `demo_data` пуст — удалятся вообще ВСЕ элементы инфоблока. Без предупреждений, при следующем хите.
>
> 2. **Правки в админке не сохранятся.** Если контент-менеджер изменит элемент или добавит новый — `strict` перезапишет данные из конфига обратно и удалит лишнее. Админка при `strict` — режим "только для чтения", по сути.
>
> **Когда использовать:** только на этапе разработки, когда инфоблок полностью управляется из кода и никто его руками не трогает.
>
> **На продакшене** ставь `strict: false` — иначе рискуешь потерять данные.
>
> Подробная матрица поведения — в [docs/versioning.md](docs/versioning.md).

## Быстрый старт

### 1. Установка

Скопируй директорию `BitrixCdd/` в `local/src/`.

В `local/php_interface/init.php` добавь автозагрузку:

```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/src/BitrixCdd/Core/Autoloader.php';

$cddLoader = new \BitrixCdd\Core\Autoloader();
$cddLoader->addNamespace('BitrixCdd', $_SERVER['DOCUMENT_ROOT'] . '/local/src/BitrixCdd');
$cddLoader->register();
```

Или через Composer:

```json
"autoload": {
    "psr-4": {
        "BitrixCdd\\": "local/src/BitrixCdd/"
    }
}
```

### 2. Инициализация

```php
use BitrixCdd\Core\Application;

$app = Application::getInstance();
$app->initialize();
```

Одна строка — и библиотека:
- Загрузит глобальный конфиг из `local/config/global.php`
- Создаст типы инфоблоков из `local/config/iblock_types/*.php`
- Создаст инфоблоки и свойства из `local/config/iblocks/*.php`
- Наполнит их демо-данными (если настроено)


### 3. Минимальный конфиг инфоблока

Примеры лежат в каталоге `examples`, просто скопируй их в `/local/config` и посмотри.

Ручной пример:

Создай `local/config/iblock_types/content.php` для типа инфоблока:

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
        ]
    ],
];
```

Тип создаётся при инициализации. Если тип уже существует и `need_sync: true` — обновляется.

Создай файл `local/config/iblocks/news.php`:

```php
<?php
return [
    'iblock' => [
        'code' => 'news',
        'type' => 'content',  // тип инфоблока (должен существовать)
        'name' => 'Новости',
    ],
    'properties' => [
        'AUTHOR' => [
            'name' => 'Автор',
            'type' => 'S',
        ],
    ],
];
```

Всё. При следующем запросе к сайту появится инфоблок "Новости" со свойством "Автор".

### 4. Получение данных в компоненте

```php
$app = \BitrixCdd\Core\Application::getInstance();
$extractor = $app->getService('iblock.data_extractor');

$items = $extractor->getElements('news', ['ACTIVE' => 'Y'], ['limit' => 10]);

foreach ($items as $item) {
    echo $item['NAME'];
    echo $item['AUTHOR']; // уже строка, не массив с VALUE
}
```

---

## Полный пример: от конфига до компонента

### 1. Тип инфоблока

`local/config/iblock_types/content.php`:

```php
<?php
return [
    'id' => 'content',
    'sections' => 'N',
    'lang' => [
        'ru' => ['NAME' => 'Контент', 'ELEMENT_NAME' => 'Элементы'],
    ],
];
```

### 2. Глобальный конфиг

`local/config/global.php`:

```php
<?php
return [
    'need_sync' => true,
    'strict' => false,
    'iblock_fields' => [
        'CODE' => [
            'DEFAULT_VALUE' => [
                'TRANSLITERATION' => 'Y',
                'TRANS_LEN' => 100,
                'TRANS_CASE' => 'L',
                'TRANS_SPACE' => '-',
                'TRANS_OTHER' => '-',
                'TRANS_EAT' => 'Y',
                'USE_GOOGLE' => 'N',
            ],
        ],
    ],
];
```

### 3. Конфиг инфоблока

`local/config/iblocks/team.php`:

```php
<?php
return [
    'version' => '1.0',
    'strict' => true,   // Полный контроль из конфига

    'iblock' => [
        'code' => 'team',
        'type' => 'content',
        'name' => 'Команда',
    ],

    'properties' => [
        'POSITION' => [
            'name' => 'Должность',
            'type' => 'S',
            'required' => true,
            'validation' => ['max_length' => 100],
        ],
        'PHOTO' => [
            'name' => 'Фото',
            'type' => 'F',
            'file_type' => 'jpg,png,webp',
        ],
        'EMAIL' => [
            'name' => 'Email',
            'type' => 'S',
        ],
    ],

    'fields' => [
        'NAME'     => ['type' => 'standard'],
        'SORT'     => ['type' => 'standard'],
        'POSITION' => ['type' => 'property', 'property_type' => 'string'],
        'PHOTO'    => ['type' => 'property', 'property_type' => 'file'],
        'EMAIL'    => ['type' => 'property', 'property_type' => 'string'],
    ],

    'demo_data' => [
        [
            'code' => 'ivan-ivanov',
            'name' => 'Иван Иванов',
            'sort' => 100,
            'properties' => [
                'POSITION' => 'Директор',
                'PHOTO' => '/local/config/demo_data/team/ivan.webp',
                'EMAIL' => 'ivan@example.com',
            ],
        ],
    ],
];
```

### 4. Инициализация

`local/php_interface/init.php`:

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/src/BitrixCdd/Core/Autoloader.php';

$cddLoader = new \BitrixCdd\Core\Autoloader();
$cddLoader->addNamespace('BitrixCdd', $_SERVER['DOCUMENT_ROOT'] . '/local/src/BitrixCdd');
$cddLoader->register();

$app = \BitrixCdd\Core\Application::getInstance();
$app->initialize();

// Валидация (опционально)
$validation = $app->getService('validation');
$configService = $app->getService('iblock.config');
$config = $configService->getConfig('team');
if ($config) {
    $validation->registerFromConfig('team', $config);
}
$validation->initEventHandlers();
```

### 5. Компонент

`local/components/easy/team.list/class.php`:

```php
<?php
use BitrixCdd\Core\Application;
use BitrixCdd\Infrastructure\BaseComponent;

class TeamListComponent extends BaseComponent
{
    public function executeComponent()
    {
        \Bitrix\Main\Loader::requireModule('iblock');

        $app = Application::getInstance();
        $extractor = $app->getService('iblock.data_extractor');

        $this->arResult['ITEMS'] = $extractor->getElements(
            'team',
            ['ACTIVE' => 'Y'],
            ['order' => ['SORT' => 'ASC']]
        );

        parent::executeComponent();
    }
}
```

### 6. Шаблон компонента

`local/components/easy/team.list/templates/.default/template.php`:

```php
<?php if (!empty($arResult['ITEMS'])): ?>
<div class="team">
    <?php foreach ($arResult['ITEMS'] as $member): ?>
    <div class="team__card">
        <?php if ($member['PHOTO']): ?>
            <img src="<?= $member['PHOTO'] ?>" alt="<?= htmlspecialchars($member['NAME']) ?>">
        <?php endif; ?>
        <h3><?= htmlspecialchars($member['NAME']) ?></h3>
        <p><?= htmlspecialchars($member['POSITION']) ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
```
---

# Детальное описание конфигурации

## Структура конфигов

```
local/config/
├── global.php              # Глобальные настройки (мержатся во все конфиги)
├── iblock_types/           # Типы инфоблоков (один файл = один тип)
│   └── content.php
└── iblocks/                # Инфоблоки (один файл = один инфоблок)
    └── news.php
```

Конфигурация есть глобальная и локальная(для каждого отдельного инфоблока). У локальной приоритет очевидно выше.

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
    'strict'    => false,   // Удалять лишние свойства/элементы (НЕБЕЗОПАСНО, только для dev)

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


## Более подробная документация по отдельным частям

| Раздел | Описание |
|--------|----------|
| **[Демо-данные](docs/demo-data.md)** | Формат demo_data, автоматическая конвертация значений |
| **[Получение данных](docs/data-extractor.md)** | ElementDataExtractor, fields, property_type, file_info, CRUD-операции |
| **[Версионирование](docs/versioning.md)** | version, need_sync, strict, матрица поведения, priority |
| **[Валидация](docs/validation.md)** | Встроенные правила, активация, свои правила |
| **[Сервисы и API](docs/services.md)** | DI-контейнер, highload-блоки, шаблоны сайта, BaseComponent |

---

## FAQ

### Когда нужно менять version?

Когда меняешь структуру: добавляешь/удаляешь свойства, меняешь типы. Не нужно менять при правке демо-данных (если `strict: false`).

### Почему инфоблок не создаётся?

Проверь:
1. Тип инфоблока существует (или есть его конфиг в `iblock_types/`).
2. `code`, `type` и `name` заполнены в секции `iblock`.
3. Нет ошибок в `error_log` — все исключения логируются туда.

### Почему свойства не обновляются?

Если `need_sync: false` или версия не изменилась — структура считается актуальной и не обновляется. Увеличь `version`. Ес


### Почему ElementDataExtractor возвращает пустой массив?

1. Инфоблок не зарегистрирован (нет конфига или ошибка при загрузке).
2. В конфиге нет секции `fields` — без неё extractor не знает, какие поля возвращать.
3. Нет активных элементов по заданному фильтру.

### Как сбросить версию (пересинхронизировать всё)?

Удали запись из таблицы `cdd_iblock_versions` для нужного инфоблока. При следующем хите структура пересоздастся.

### Зачем нужен priority?

Если инфоблок A ссылается на инфоблок B через `link_iblock_id`, то B должен создаться первым. Задай B меньший priority.
