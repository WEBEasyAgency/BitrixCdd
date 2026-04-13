# BitrixCdd -- Config-Driven Development для Битрикс

Библиотека, которая позволяет описывать инфоблоки, свойства и демо-данные в PHP-конфигах. Конфиги лежат в репозитории, версионируются через git, и синхронизируются с БД автоматически.

## Какие проблемы решает

- **Миграции** -- структура инфоблоков описана в коде и создается одинаково на всех окружениях.
- **Ручная работа в админке** -- не нужно создавать инфоблоки, свойства и тестовый контент руками. Описал в конфиге -- при первом хите все появится.
- **Рассинхрон между окружениями** -- версионирование конфигов гарантирует, что изменения применятся ровно один раз.
- **Валидация** -- позволяет валидировать поля в админке на обязательность, ограничения по символам и т.д.
- **Единый источник данных для компонентов** -- `ElementDataExtractor` сам конвертирует ID файлов в URL, enum-ы в текст и т.д.

> ### sync_mode: прочитай перед использованием
>
> Режим синхронизации определяет, насколько агрессивно библиотека управляет данными:
>
> | Режим | Структура | Демо-данные | Удаление лишнего |
> |----------|-----------|-------------|------------------|
> | `off` | Раз на version | Никогда | Нет |
> | `soft` | Раз на version | Раз (create only) | Нет |
> | `ensure` | Раз на version | Создать/дозаполнить пустое | Нет |
> | `once` | Раз на version | Force (если есть demo_data) | Да |
> | `danger` | Каждый хит | Каждый хит (force) | Да |
>
> **На продакшене** используй `soft` или `ensure`. Режим `danger` перезаписывает любые правки из админки при каждом хите.
> Инфоблок будет существовать **ровно** в том виде, как он описан в конфигурации. Любые попытки его изменить, включая ручные правки в админке будут тщетны.
> Подробнее -- в [docs/configuration.md](docs/configuration.md) и [docs/versioning.md](docs/versioning.md).

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

Одна строка -- и библиотека:
- Загрузит глобальный конфиг из `local/config/global.php`
- Создаст типы инфоблоков из `local/config/iblock_types/*.php`
- Создаст инфоблоки и свойства из `local/config/iblocks/*.php`
- Наполнит их демо-данными (если настроено)


### 3. Конфиг инфоблока

Примеры лежат в каталоге `examples/`, просто скопируй их в `/local/config/` и посмотри.

Базовый пример вручную:

`local/config/global.php`:

```php
<?php
return [
    'default_iblock_type' => 'content',
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

`local/config/iblocks/post.php`:

```php
<?php
return [
    'include_standard_fields' => true,
    
    'iblock' => [
        'code' => 'post',
        'name' => 'Посты блога',
    ],
    
    'properties' => [
        'AUTHOR' => ['name' => 'Автор'],
    ],
    
    'demo_data' => [
        [
            'code' => 'first-post',
            'name' => 'Первый пост',
            'detail_text' => 'Lorem ipsum',
            'properties' => [
                'AUTHOR' => 'Иван Иванов',
            ],
        ],
    ],
];
```

При следующем запросе к сайту появится тип инфоблока "Контент" (если не существовал), инфоблок "Посты блога" со свойством "Автор" и демо-элементом "Первый пост" автора Иван Иванов и детальным текстом 'Lorem ipsum'.

### 4. Получение данных

```php
$app = \BitrixCdd\Core\Application::getInstance();
$extractor = $app->getService('iblock.data_extractor');

$items = $extractor->getElements('post'); 
// $items = $extractor->getElements('post', ['ACTIVE' => 'Y'], ['limit' => 10]); // или с фильтром/параметрами

foreach ($items as $item) {
    echo $item['NAME'];     // стандартное поле (include_standard_fields)
    echo $item['AUTHOR'];   
}
```

---

## Полный пример: от конфига до компонента

### 1. Конфиг инфоблока

`local/config/iblocks/team.php`:

```php
<?php
return [
    'sync_mode' => 'once',

    'iblock' => [
        'code' => 'team',
        'name' => 'Команда',
    ],

    'include_standard_fields' => ['NAME', 'SORT'],

    'properties' => [
        'POSITION' => [
            'name' => 'Должность',
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
        ],
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

### 2. Компонент

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

### 3. Шаблон компонента

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

## Документация

| Раздел | Описание |
|--------|----------|
| **[Глобальный конфиг](docs/global-config.md)** | global.php, iblock_fields, транслитерация CODE |
| **[Конфигурация](docs/configuration.md)** | sync_mode, формат конфигов, типы свойств, программная регистрация |
| **[Демо-данные](docs/demo-data.md)** | Формат demo_data, автоматическая конвертация значений |
| **[Получение данных](docs/data-extractor.md)** | ElementDataExtractor, property_type, file_info, CRUD |
| **[Версионирование](docs/versioning.md)** | version, sync_mode и версии, priority, legacy need_sync/strict |
| **[Валидация](docs/validation.md)** | Встроенные правила, активация, свои правила |
| **[Сервисы и API](docs/services.md)** | DI-контейнер, highload-блоки, шаблоны сайта, BaseComponent |
| **[Экспорт из существующего проекта](docs/export.md)** | Интеграция в существующие проекты, экспорт инфоблоков в конфиги |

---

## FAQ

### Когда нужно менять version?

Когда меняешь структуру: добавляешь/удаляешь свойства, меняешь типы. В режиме `soft` менять version при правке демо-данных бесполезно -- они создаются один раз.

### Почему инфоблок не создается?

Проверь:
1. `code` и `name` заполнены в секции `iblock`.
2. Тип инфоблока задан (в конфиге или через `default_iblock_type` в global.php).
3. Нет ошибок в `error_log`.

### Почему свойства не обновляются?

Если версия не изменилась -- структура считается актуальной. Увеличь `version`.

### Почему ElementDataExtractor возвращает пустой массив?

1. Инфоблок не зарегистрирован (нет конфига или ошибка при загрузке).
2. В конфиге нет `properties` -- без них extractor не знает, какие поля возвращать.
3. Нет активных элементов по заданному фильтру.

### Как сбросить версию (пересинхронизировать все)?

Удали запись из таблицы `cdd_iblock_versions` для нужного инфоблока. При следующем хите структура пересоздастся.

### Как мигрировать с need_sync/strict на sync_mode?

Просто добавь `sync_mode` в конфиг. Старые `need_sync`/`strict` продолжают работать для обратной совместимости, но `sync_mode` имеет приоритет. Маппинг описан в [docs/versioning.md](docs/versioning.md).

### Зачем нужен priority?

Если инфоблок A ссылается на инфоблок B через `link_iblock_id`, то B должен создаться первым. Задай B меньший priority.
