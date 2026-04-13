# ElementDataExtractor -- получение данных

Вместо того чтобы писать `CIBlockElement::GetList` с кучей `GetProperty` и руками конвертировать ID файлов в URL -- описываешь свойства в конфиге, а `ElementDataExtractor` делает всё сам.

## Использование в компоненте

```php
<?php
use Bitrix\Main\Loader;
use BitrixCdd\Core\Application;
use BitrixCdd\Infrastructure\BaseComponent;

class NewsListComponent extends BaseComponent
{
    public function executeComponent()
    {
        Loader::requireModule('iblock');

        if ($this->startResultCache($this->arParams['CACHE_TIME'] ?? 3600)) {
            $app = Application::getInstance();
            $extractor = $app->getService('iblock.data_extractor');

            $this->arResult['ITEMS'] = $extractor->getElements(
                'news',
                ['ACTIVE' => 'Y'],
                ['limit' => 10, 'order' => ['SORT' => 'ASC']]
            );

            if (empty($this->arResult['ITEMS'])) {
                $this->abortResultCache();
                return;
            }

            $this->includeComponentTemplate();
        }
    }
}
```

В шаблоне данные уже преобразованы:

```php
<?php foreach ($arResult['ITEMS'] as $item): ?>
    <h2><?= htmlspecialchars($item['NAME']) ?></h2>
    <img src="<?= $item['PREVIEW_PICTURE'] ?>">   <!-- URL, не ID -->
    <p><?= htmlspecialchars($item['AUTHOR']) ?></p> <!-- строка, не массив -->
    <p><?= $item['BODY'] ?></p>                     <!-- текст, не ['TEXT'=>...] -->
<?php endforeach; ?>
```

## Какие поля попадают в выдачу

Extractor возвращает поля, определённые двумя способами:

1. **Свойства из `properties`** -- попадают всегда. Тип трансформации (`property_type`) выводится автоматически из типа свойства.
2. **Стандартные поля Битрикса** -- попадают если заданы через `include_standard_fields`.

Подробнее о настройке -- в [configuration.md](configuration.md#include_standard_fields).

## Таблица преобразований property_type

| `property_type` | Одиночное | Множественное | Пустое |
|-----------------|-----------|---------------|--------|
| `string` | `string` | `string[]` | `''` |
| `file` | `string` (URL) | `string[]` (URL'ы) | `''` (одиночное) / `[]` (множественное) |
| `file_info` | `array\|null` | `array[]` | `null` |
| `enum` | `string\|null` | `string[]` | `null` |
| `element` | `int\|null` | `int[]` | `[]` |
| `text` | `string` | `string[]` | `''` |

Для стандартных полей: `PREVIEW_PICTURE` и `DETAIL_PICTURE` автоматически конвертируются из ID в URL. Остальные стандартные поля возвращаются как есть.

Автоматическое определение `property_type` по типу свойства:

| type | user_type | property_type |
|------|-----------|---------------|
| S | - | string |
| S | HTML | text |
| N | - | string |
| L | - | enum |
| F | - | file |
| E | - | element |
| G | - | element |

Если нужен нестандартный маппинг (например `file_info` вместо `file`), задай секцию `fields` явно -- см. [configuration.md](configuration.md#расширенная-настройка-полей-fields).

## file_info -- расширенная информация о файле

Тип `file_info` возвращает не просто URL, а массив с дополнительными данными:

```php
// В секции fields (явное задание)
'DOCUMENT' => ['type' => 'property', 'property_type' => 'file_info'],

// Результат
$item['DOCUMENT'] = [
    'SRC'           => '/upload/iblock/abc/file.pdf',
    'SIZE'          => 1048576,
    'FILE_SIZE'     => 'PDF, 1 MB',
    'ORIGINAL_NAME' => 'document.pdf',
];
```

Полезно для скачиваемых файлов, где нужно показать размер и формат.

---

## CRUD-операции (IBlockConfigManager)

Для работы с элементами конкретного инфоблока есть `IBlockConfigManager`. Получить его можно через `IBlockConfigService`:

```php
$configService = $app->getService('iblock.config');
$manager = $configService->getManager('news');

// Получение элементов
$elements = $manager->getElements([
    'filter' => ['ACTIVE' => 'Y'],
    'order'  => ['SORT' => 'ASC'],
    'select' => ['ID', 'NAME', 'SORT'],
    'limit'  => 10,
]);

// Один элемент
$element = $manager->getElementById(42);

// Создание
$id = $manager->createElement(
    ['NAME' => 'Новая статья', 'ACTIVE' => 'Y'],
    ['AUTHOR' => 'Admin']
);

// Обновление
$manager->updateElement($id, ['NAME' => 'Обновлённая'], ['AUTHOR' => 'Editor']);

// Удаление
$manager->deleteElement($id);

// Количество элементов
$count = $manager->getCount();

// ID инфоблока
$iblockId = $manager->getIBlockId();
```

### Когда что использовать

- **`ElementDataExtractor`** -- для чтения данных в компонентах (с автоматической конвертацией типов).
- **`IBlockConfigManager`** -- для CRUD-операций (создание, обновление, удаление элементов). Возвращает сырые данные Bitrix.
