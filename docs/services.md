# Сервисы и API

## DI-контейнер

После `$app->initialize()` доступны сервисы:

| Сервис | Класс | Что делает |
|--------|-------|-----------|
| `iblock.config` | `IBlockConfigService` | Загрузка конфигов, регистрация инфоблоков |
| `iblock.data_extractor` | `ElementDataExtractor` | Чтение элементов с конвертацией типов |
| `hlblock.registration` | `HighloadBlockRegistrationService` | Создание/управление HL-блоками |
| `template.registration` | `TemplateRegistrationService` | Привязка шаблонов к разделам |
| `validation` | `ValidationService` | Валидация свойств |

```php
$app = Application::getInstance();

$service = $app->getService('iblock.config');   // получить
$app->hasService('validation');                  // проверить (bool)
$app->registerService('my.service', $instance);  // зарегистрировать свой
$app->removeService('my.service');               // удалить
```

---

## Highload-блоки

Для создания и управления highload-блоками:

```php
use BitrixCdd\Domain\Entities\HighloadBlockEntity;

$hlService = $app->getService('hlblock.registration');

// Создание
$hlblock = new HighloadBlockEntity(
    name: 'Colors',              // PascalCase, без пробелов
    tableName: 'app_colors',     // Имя таблицы в БД
);
$hlblockId = $hlService->register($hlblock);

// Массовая регистрация
$ids = $hlService->registerMultiple([
    new HighloadBlockEntity('Colors', 'app_colors'),
    new HighloadBlockEntity('Statuses', 'app_statuses'),
]);
// $ids = ['Colors' => 1, 'Statuses' => 2]

// Проверка / получение / удаление
$hlService->exists('Colors');          // bool
$hlService->getIdByName('Colors');     // int|null
$hlService->delete('Colors');
```

`register()` идемпотентен: если HL-блок с таким именем уже есть -- вернёт его ID.

---

## Шаблоны сайта

Программная привязка шаблонов к разделам:

```php
use BitrixCdd\Domain\Entities\TemplateEntity;

$templateService = $app->getService('template.registration');

// Привязка шаблона к разделу
$templateService->register(new TemplateEntity(
    path: '/cabinet/',        // Путь раздела
    template: 'cabinet',      // Директория шаблона в /local/templates/
    condition: 'exact',       // Тип условия (по умолчанию 'exact')
    sort: 50,                 // Сортировка (по умолчанию 100, меньше = приоритетнее)
    siteId: 's1',             // ID сайта (по умолчанию 's1')
));

// Массовая регистрация
$templateService->registerMultiple([
    new TemplateEntity('/cabinet/', 'cabinet', sort: 50),
    new TemplateEntity('/landing/', 'landing', sort: 60),
    new TemplateEntity('/', 'main', sort: 100),
]);

// Проверка
$templateService->exists('/cabinet/');  // bool
```

Существующие привязки не затираются. Если привязка с таким же условием уже есть -- обновляется.

---

## BaseComponent

Базовый класс для компонентов, работающих с CDD-инфоблоками. Наследуется от `CBitrixComponent`, предоставляет хелперы. `executeComponent()` пишется как обычно.

### $iblockCode

Код инфоблока, с которым работает компонент. Задаётся в классе или через параметр `IBLOCK_CODE`. Используется всеми хелперами ниже.

```php
class MyComponent extends BaseComponent {
    protected string $iblockCode = 'news';
}
```

### getItems($filter, $order, $limit)

Получить элементы через ElementDataExtractor. Заменяет типовой бойлерплейт с `Application::getInstance()` + `getService()` + `getElements()`.

```php
// Без BaseComponent
$app = Application::getInstance();
$extractor = $app->getService('iblock.data_extractor');
$items = $extractor->getElements('news', ['ACTIVE' => 'Y'], ['order' => ['SORT' => 'ASC']]);

// С BaseComponent
$items = $this->getItems(order: ['SORT' => 'ASC']);
```

Фильтр по умолчанию `['ACTIVE' => 'Y']`. Для получения всех элементов передай пустой массив: `$this->getItems(filter: [])`.

### registerCacheTags()

Регистрирует тег `iblock_id_X` для тегированного кеша. При изменении элемента инфоблока Битрикс автоматически сбросит кеш компонента, не дожидаясь истечения `CACHE_TIME`. Вызывать внутри `startResultCache`-блока.

```php
// Без BaseComponent
if (defined('BX_COMP_MANAGED_CACHE')) {
    $app = Application::getInstance();
    $configService = $app->getService('iblock.config');
    $manager = $configService->getManager('news');
    $taggedCache = \Bitrix\Main\Application::getInstance()->getTaggedCache();
    $taggedCache->registerTag('iblock_id_' . $manager->getIBlockId());
}

// С BaseComponent
$this->registerCacheTags();
```

### getIblockId(), getManager(), getExtractor()

Доступ к сервисам без обращения к Application:

```php
$iblockId = $this->getIblockId();     // ID инфоблока
$manager  = $this->getManager();      // IBlockConfigManager (CRUD)
$extractor = $this->getExtractor();   // ElementDataExtractor
```

### render($templatePage)

Замена `$this->includeComponentTemplate()`. Выводит шаблон и автоматически добавляет debug-панель (если `DEBUG=Y` и пользователь -- администратор). Debug рендерится в свёрнутый `<details>` с JSON-содержимым `$arResult` и `$arParams`.

```php
$APPLICATION->IncludeComponent('easy:news.list', '', ['DEBUG' => 'Y']);
```

### CACHE_TIME

По умолчанию 3600 секунд. Задаётся в параметрах компонента, дефолт обрабатывается в BaseComponent.

### Пример

```php
<?php
use Bitrix\Main\Loader;
use BitrixCdd\Infrastructure\BaseComponent;

class MainBannerComponent extends BaseComponent
{
    protected string $iblockCode = 'main_banner';

    public function executeComponent()
    {
        Loader::requireModule('iblock');
        $this->arResult['ITEMS'] = $this->getItems(order: ['SORT' => 'ASC']);
        $this->render();
    }
}
```

### Пример с кешированием

```php
public function executeComponent()
{
    Loader::requireModule('iblock');

    if ($this->startResultCache($this->arParams['CACHE_TIME'])) {
        $this->arResult['ITEMS'] = $this->getItems(order: ['SORT' => 'ASC']);

        if (empty($this->arResult['ITEMS'])) {
            $this->abortResultCache();
            return;
        }

        $this->registerCacheTags();
        $this->render();
    }
}
```

`CACHE_TIME` по умолчанию 3600 секунд. `registerCacheTags()` привязывает кеш к инфоблоку -- при изменении элемента в админке кеш сбрасывается автоматически.

---

## ListComponent

Наследуется от BaseComponent. Добавляет постраничную навигацию.

### Хелперы

| Метод | Что делает |
|-------|-----------|
| `getPagedItems($filter, $order)` | Получить элементы текущей страницы + заполнить `$arResult['NAV']` |
| `getCurrentPage()` | Номер страницы из GET-параметра `page` или параметра `PAGE_NUM` |

### $arResult['NAV']

| Ключ | Тип | Описание |
|------|-----|----------|
| `TOTAL` | int | Общее количество элементов |
| `PAGE` | int | Текущая страница |
| `PAGE_SIZE` | int | Элементов на странице |
| `TOTAL_PAGES` | int | Всего страниц |
| `HAS_PREV` | bool | Есть предыдущая страница |
| `HAS_NEXT` | bool | Есть следующая страница |

### Параметры

| Параметр | По умолчанию | Описание |
|----------|--------------|----------|
| `PAGE_SIZE` | 10 | Элементов на странице. Также можно задать через `protected int $pageSize` |
| `PAGE_NUM` | из GET `page` | Номер страницы |

### Пример

```php
<?php
use Bitrix\Main\Loader;
use BitrixCdd\Infrastructure\ListComponent;

class NewsListComponent extends ListComponent
{
    protected string $iblockCode = 'news';
    protected int $pageSize = 12;

    public function executeComponent()
    {
        Loader::requireModule('iblock');
        $this->arResult['ITEMS'] = $this->getPagedItems(order: ['SORT' => 'ASC']);
        $this->render();
    }
}
```

В шаблоне:

```php
<?php foreach ($arResult['ITEMS'] as $item): ?>
    <h2><?= htmlspecialchars($item['NAME']) ?></h2>
<?php endforeach; ?>

<?php if ($arResult['NAV']['TOTAL_PAGES'] > 1): ?>
    <nav>
        <?php if ($arResult['NAV']['HAS_PREV']): ?>
            <a href="?page=<?= $arResult['NAV']['PAGE'] - 1 ?>">Назад</a>
        <?php endif; ?>
        <span>Страница <?= $arResult['NAV']['PAGE'] ?> из <?= $arResult['NAV']['TOTAL_PAGES'] ?></span>
        <?php if ($arResult['NAV']['HAS_NEXT']): ?>
            <a href="?page=<?= $arResult['NAV']['PAGE'] + 1 ?>">Вперёд</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
```

---

## DetailComponent

Наследуется от BaseComponent. Получает один элемент по CODE или ID.

### Хелперы

| Метод | Что делает |
|-------|-----------|
| `getItem()` | Получить элемент по `ELEMENT_CODE` или `ELEMENT_ID` из параметров |
| `set404()` | Установить 404 |

### Параметры

| Параметр | Описание |
|----------|----------|
| `ELEMENT_CODE` | Символьный код элемента (приоритет) |
| `ELEMENT_ID` | ID элемента |

### Пример

```php
<?php
use Bitrix\Main\Loader;
use BitrixCdd\Infrastructure\DetailComponent;

class NewsDetailComponent extends DetailComponent
{
    protected string $iblockCode = 'news';

    public function executeComponent()
    {
        Loader::requireModule('iblock');

        $this->arResult['ITEM'] = $this->getItem();

        if (!$this->arResult['ITEM']) {
            $this->set404();
            return;
        }

        $this->render();
    }
}
```

Вызов:

```php
$APPLICATION->IncludeComponent('easy:news.detail', '', [
    'ELEMENT_CODE' => $arResult['VARIABLES']['ELEMENT_CODE'],
]);
```
