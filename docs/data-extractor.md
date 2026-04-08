# ElementDataExtractor — получение данных

Главная фишка для компонентов. Вместо того чтобы писать `CIBlockElement::GetList` с кучей `GetProperty` и руками конвертировать ID файлов в URL — описываешь поля в конфиге, а `ElementDataExtractor` делает всё сам.

## Настройка в конфиге

Секция `fields` описывает, какие поля доступны и как их обрабатывать:

```php
'fields' => [
    // Стандартные поля Bitrix (NAME, PREVIEW_TEXT, SORT и т.д.)
    'NAME'            => ['type' => 'standard'],
    'PREVIEW_PICTURE' => ['type' => 'standard'],     // Файловые → ID автоматом в URL
    'DETAIL_PICTURE'  => ['type' => 'standard'],     // Тоже файловые → URL

    // Свойства инфоблока (property_type определяет трансформацию)
    'AUTHOR'  => ['type' => 'property', 'property_type' => 'string'],
    'IMAGE'   => ['type' => 'property', 'property_type' => 'file'],
    'AVATAR'  => ['type' => 'property', 'property_type' => 'file_info'],
    'TAGS'    => ['type' => 'property', 'property_type' => 'enum'],
    'LINKED'  => ['type' => 'property', 'property_type' => 'element'],
    'BODY'    => ['type' => 'property', 'property_type' => 'text'],
],
```

## Использование в компоненте

```php
<?php
// local/components/easy/news.list/class.php

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

        if ($this->arParams['DEBUG'] === 'Y') {
            echo '<pre>' . var_export($this->arResult, true) . '</pre>';
        }
    }
}
```

В шаблоне данные уже преобразованы — можно использовать напрямую:

```php
<?php // local/components/easy/news.list/templates/.default/template.php ?>
<?php foreach ($arResult['ITEMS'] as $item): ?>
    <h2><?= htmlspecialchars($item['NAME']) ?></h2>
    <img src="<?= $item['PREVIEW_PICTURE'] ?>">   <!-- URL, не ID -->
    <p><?= htmlspecialchars($item['AUTHOR']) ?></p> <!-- строка, не массив -->
    <p><?= $item['BODY'] ?></p>                     <!-- текст, не ['TEXT'=>...] -->
<?php endforeach; ?>
```

## Таблица преобразований property_type

| `property_type` | Одиночное | Множественное | Пустое |
|-----------------|-----------|---------------|--------|
| `string` | `string` | `string[]` | `''` |
| `file` | `string` (URL) | `string[]` (URL'ы) | `''` (одиночное) / `[]` (множественное) |
| `file_info` | `array\|null` | `array[]` | `null` |
| `enum` | `string\|null` | `string[]` | `null` |
| `element` | `int\|null` | `int[]` | `[]` |
| `text` | `string` | `string[]` | `''` |

Для `standard` полей: `PREVIEW_PICTURE` и `DETAIL_PICTURE` автоматически конвертируются из ID в URL. Остальные стандартные поля возвращаются как есть.

## file_info — расширенная информация о файле

Тип `file_info` возвращает не просто URL, а массив с дополнительными данными:

```php
// Конфиг
'DOCUMENT' => ['type' => 'property', 'property_type' => 'file_info'],

// Результат
$item['DOCUMENT'] = [
    'SRC'           => '/upload/iblock/abc/file.pdf',   // URL файла
    'SIZE'          => 1048576,                         // Размер в байтах
    'FILE_SIZE'     => 'PDF, 1 MB',                    // Человекочитаемый размер
    'ORIGINAL_NAME' => 'document.pdf',                  // Оригинальное имя файла
];
```

Полезно для скачиваемых файлов, где нужно показать размер и формат.

> **Важно**: Если секция `fields` не определена в конфиге инфоблока, `ElementDataExtractor` вернёт пустой массив. Не забудь описать поля, которые хочешь получить.

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
    ['NAME' => 'Новая статья', 'ACTIVE' => 'Y'],   // Поля
    ['AUTHOR' => 'Admin']                            // Свойства
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

- **`ElementDataExtractor`** — для чтения данных в компонентах (с автоматической конвертацией типов).
- **`IBlockConfigManager`** — для CRUD-операций (создание, обновление, удаление элементов). Возвращает сырые данные Bitrix.
