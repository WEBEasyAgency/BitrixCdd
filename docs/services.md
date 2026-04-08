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

Пока не реализованы на уровне конфигов, но работа с ними в Bitrix тот ещё прикол, так что уже есть единый адаптер.

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

`register()` идемпотентен: если HL-блок с таким именем уже есть — просто вернёт его ID.

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

Существующие привязки не затираются. Если привязка с таким же условием уже есть — обновляется. Список сортируется по полю SORT.

---

## Базовый компонент (BaseComponent)

Удобный базовый класс для создания компонентов. Наследуется от `CBitrixComponent` и добавляет режим отладки.

### Пример компонента

```php
<?php
use BitrixCdd\Core\Application;
use BitrixCdd\Infrastructure\BaseComponent;

class MainBannerComponent extends BaseComponent
{
    public function executeComponent()
    {
        \Bitrix\Main\Loader::requireModule('iblock');

        if ($this->startResultCache($this->arParams['CACHE_TIME'] ?? 3600)) {
            $app = Application::getInstance();
            $extractor = $app->getService('iblock.data_extractor');

            $this->arResult['ITEMS'] = $extractor->getElements(
                'main_banner',
                ['ACTIVE' => 'Y'],
                ['order' => ['SORT' => 'ASC']]
            );

            if (empty($this->arResult['ITEMS'])) {
                $this->abortResultCache();
                return;
            }

            $this->includeComponentTemplate();
        }

        // Отладка: добавь ?DEBUG=Y в URL
        if ($this->arParams['DEBUG'] === 'Y') {
            echo '<pre>' . var_export($this->arResult, true) . '</pre>';
        }
    }
}
```

### Режим отладки

Передай параметр `DEBUG=Y` компоненту — и он выведет содержимое `$arResult` и `$arParams` в стилизованном блоке `<pre>`:

```php
$APPLICATION->IncludeComponent('easy:main.banner', '', ['DEBUG' => 'Y']);
```

Если наследуешь `BaseComponent` и вызываешь `parent::executeComponent()` — отладка подключается автоматически.
