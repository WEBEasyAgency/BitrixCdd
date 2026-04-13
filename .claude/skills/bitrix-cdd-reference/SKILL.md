---
name: bitrix-cdd-reference
description: >
  BitrixCdd -- Config-Driven Development библиотека для Bitrix CMS.
  Описание конфигов, умолчаний, компонентов и сервисов.
  Используй при работе с файлами в local/config/, local/src/BitrixCdd/,
  при создании инфоблоков, свойств, компонентов или конфигов CDD.
autoContext:
  - path: "local/config/**/*.php"
  - path: "local/src/BitrixCdd/**/*.php"
  - path: "local/components/**/*.php"
---

# BitrixCdd Reference

## Конфиг инфоблока

Файлы в `local/config/iblocks/*.php`. Минимальный конфиг:

```php
return [
    'iblock' => [
        'code' => 'news',      // обязательно
        'name' => 'Новости',   // обязательно
    ],
    'properties' => [
        'AUTHOR' => ['name' => 'Автор'],
    ],
];
```

### Умолчания

| Ключ | Значение по умолчанию |
|------|----------------------|
| `version` | `'1.0'` |
| `sync_mode` | `'soft'` |
| `priority` | `500` |
| `iblock.type` | из `default_iblock_type` в global.php |
| `iblock.sort` | `500` |
| property `type` | `'S'` (строка) |
| property `required` | `false` |
| property `multiple` | `false` |

### Типы свойств

| type | Описание |
|------|----------|
| `S` | Строка (по умолчанию). С `user_type`: Date, DateTime, HTML |
| `N` | Число |
| `L` | Список. `values: ['Да', 'Нет']` или полный формат с SORT/DEF |
| `F` | Файл. `file_type: 'jpg,png,webp'` |
| `E` | Привязка к элементу. `link_iblock_id: 'code'` |
| `G` | Привязка к разделу. `link_iblock_id: 'code'` |

### Опции свойства

| Ключ | Описание |
|------|----------|
| `name` | Обязательно. Название |
| `type` | Тип. По умолчанию 'S' |
| `sort` | Сортировка |
| `required` | Обязательность |
| `multiple` | Множественность |
| `user_type` | Date, DateTime, HTML |
| `file_type` | Расширения файлов |
| `link_iblock_id` | Код связанного инфоблока |
| `values` | Значения enum (для type: 'L') |
| `typograph` | true -- неразрывные пробелы после предлогов при чтении |
| `validation` | Правила: max_length, min, max_count |

### include_standard_fields

Какие стандартные поля Битрикса попадают в выдачу ElementDataExtractor.

```php
'include_standard_fields' => true,                  // все 6 полей
'include_standard_fields' => ['NAME', 'SORT'],      // выборочно
// false (по умолчанию) -- только properties
```

Стандартные поля: NAME, SORT, PREVIEW_TEXT, PREVIEW_PICTURE, DETAIL_TEXT, DETAIL_PICTURE.

## Demo data

Ключ `demo_data` (или `demoData`). Стандартные поля -- snake_case, свойства -- UPPER_CASE внутри `properties`.

```php
'demo_data' => [
    [
        'code' => 'first',              // обязательно
        'name' => 'Первый элемент',     // обязательно
        'active' => 'Y',               // по умолчанию 'Y'
        'sort' => 100,                 // по умолчанию 500
        'preview_text' => '...',
        'detail_text' => '...',
        'preview_picture' => '/local/config/assets/...',
        'detail_picture' => '/local/config/assets/...',
        'properties' => [
            'AUTHOR' => 'Текст',           // S -- как есть
            'STATUS' => 'Опубликовано',     // L -- текст, не ID
            'IMAGE' => '/local/config/assets/pic.jpg',  // F -- путь
            'DATE' => '2026-01-15',        // Date -- ISO
            'BODY' => '<p>HTML</p>',       // HTML -- без обёртки
            'LINKED' => 'article-code',    // E -- код или ID
        ],
    ],
],
```

## Глобальный конфиг

`local/config/global.php`:

```php
return [
    'default_iblock_type' => 'content',
    'sync_mode' => 'soft',
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

## Компоненты

### BaseComponent

Хелперы: `getItems($filter, $order, $limit)`, `getIblockId()`, `getManager()`, `getExtractor()`, `registerCacheTags()`, `render()` (шаблон + debug).

```php
class MyComponent extends BaseComponent {
    protected string $iblockCode = 'news';

    public function executeComponent() {
        Loader::requireModule('iblock');
        $this->arResult['ITEMS'] = $this->getItems(order: ['SORT' => 'ASC']);
        $this->render();
    }
}
```

### ListComponent

Пагинация. `getPagedItems($filter, $order)` заполняет `$arResult['NAV']`.
`$pageSize` -- свойство класса или параметр `PAGE_SIZE`.

### DetailComponent

Один элемент. `getItem()` по `ELEMENT_CODE` или `ELEMENT_ID`. `set404()`.

## Сервисы

```php
$app = Application::getInstance();
$app->getService('iblock.config');          // IBlockConfigService
$app->getService('iblock.data_extractor');  // ElementDataExtractor
$app->getService('validation');             // ValidationService
```

## Типограф

```php
$typographer = new \BitrixCdd\Services\Typographer();
$text = $typographer->process('Работаем в Москве');
// "Работаем в&nbsp;Москве"
```

В конфиге: `'typograph' => true` на свойстве -- обработка при чтении через ElementDataExtractor.
