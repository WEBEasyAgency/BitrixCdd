# Интеграция в существующие проекты (экспериментально)

Инструмент для переноса существующих инфоблоков в CDD-конфиги. Читает структуру и данные из БД, генерирует готовые конфиг-файлы и пакует в ZIP-архив.

## Как запустить

Скопируй `BitrixCdd/` в `local/src/` на целевом проекте. Экспорт доступен по адресу:

```
/local/src/BitrixCdd/Tools/export.php
```

### Доступ

Два способа:

1. Авторизоваться как администратор Битрикс и открыть страницу
2. Передать секретный ключ: `?key=YOUR_KEY`

Ключ задаётся константой `EXPORT_SECRET_KEY` в начале файла `export.php`. Дефолтный ключ заблокирован -- его нужно сменить перед использованием.

## Что экспортируется

### Всегда (структура)

- `global.php` -- глобальный конфиг с `sync_mode: 'off'` и транслитерацией CODE
- `iblock_types/*.php` -- типы инфоблоков с языковыми настройками
- `iblocks/*.php` -- инфоблоки со всеми свойствами

Экспортируются все инфоблоки, у которых заполнен символьный код.

### По выбору (данные)

На стартовой странице отображается список всех инфоблоков с количеством элементов. Чекбоксами выбираешь, для каких экспортировать демо-данные:

- `demo_data/*.php` -- элементы с полями и свойствами
- `assets/{iblock_code}/*` -- файлы (изображения, документы) из upload/

## Структура архива

```
config/
├── global.php
├── iblock_types/
│   ├── content.php
│   └── catalog.php
├── iblocks/
│   ├── news.php              # структура, sync_mode: 'off'
│   └── brands.php
├── demo_data/
│   ├── news.php              # данные, отдельный файл
│   └── brands.php
└── assets/
    ├── news/
    │   └── 42_preview.jpg
    └── brands/
        ├── 990_logo.webp
        └── 991_icon.webp
```

## Как использовать результат

### 1. Распаковать архив

Содержимое `config/` из архива скопировать в `local/config/` проекта. Если `local/config/` уже существует -- мержить вручную.

### 2. Подключить demo_data

Экспортированные конфиги инфоблоков генерируются с `sync_mode: 'off'` (только структура). Чтобы подключить данные, добавь в конфиг инфоблока:

```php
// iblocks/news.php
return [
    'sync_mode' => 'soft',  // изменить с 'off' на 'soft'
    'iblock' => [...],
    'properties' => [...],
    'demo_data' => require __DIR__ . '/../demo_data/news.php',
];
```

### 3. Проверить assets

Файлы из `assets/` нужно разместить в `local/config/assets/` -- пути в demo_data уже указывают туда.

### 4. Инициализировать

```php
// local/php_interface/init.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/src/BitrixCdd/Core/Autoloader.php';

$cddLoader = new \BitrixCdd\Core\Autoloader();
$cddLoader->addNamespace('BitrixCdd', $_SERVER['DOCUMENT_ROOT'] . '/local/src/BitrixCdd');
$cddLoader->register();

$app = \BitrixCdd\Core\Application::getInstance();
$app->initialize();
```

При первом запуске библиотека сверит конфиги с БД. Если инфоблоки уже существуют (а они существуют -- мы их экспортировали), структура будет отмечена как синхронизированная.

## GET-параметры

| Параметр | Описание |
|----------|----------|
| `key` | Секретный ключ доступа |
| `types` | Фильтр по типам инфоблоков через запятую: `?types=content,catalog` |
| `demo[]` | Коды инфоблоков для экспорта данных: `?demo[]=news&demo[]=brands` |
| `action` | `preview` -- просмотр в браузере, `download` -- скачать ZIP |

## Что конвертируется в demo_data

| Тип свойства | Формат в конфиге |
|--------------|-----------------|
| S (строка) | значение как есть |
| S + Date | ISO: `2026-01-15` |
| S + DateTime | ISO: `2026-01-15 14:30:00` |
| S + HTML | текст без обёртки `['VALUE' => ...]` |
| N (число) | int или float |
| L (список) | текстовое значение, не ID |
| F (файл) | путь в assets: `/local/config/assets/...` |
| E (привязка) | символьный код элемента или ID |

## Ограничения

- Экспортируются только инфоблоки с заполненным символьным кодом (CODE)
- Свойства без кода пропускаются
- Разделы (sections) инфоблоков не экспортируются
- Для больших инфоблоков (тысячи элементов) экспорт данных может быть медленным
- Файлы копируются из upload/ -- если файл удалён с диска, в конфиге будет пропущен

## Программный доступ

```php
use BitrixCdd\Tools\ConfigExporter;

$exporter = new ConfigExporter(
    typeFilter: ['content'],           // только эти типы (пустой = все)
    demoDataIblocks: ['news', 'team']  // данные для этих инфоблоков
);

$files = $exporter->export();          // ['path' => 'content', ...]
$zip = $exporter->exportZip();         // бинарные данные ZIP
$list = $exporter->getIblockList();    // список для UI
$assets = $exporter->getAssetFiles();  // [archivePath => absolutePath]
```
