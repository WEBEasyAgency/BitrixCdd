# Система валидации свойств

Декларативные правила валидации для свойств инфоблоков. Проверка происходит при сохранении элементов через админку Bitrix — если правила нарушены, элемент не сохранится.

## Быстрый старт

### 1. Добавь правила в конфиг инфоблока

В файле `local/config/iblocks/*.php`:

```php
'properties' => [
    'TITLE' => [
        'name' => 'Заголовок',
        'type' => 'S',
        'validation' => [
            'max_length' => 90,
        ],
    ],
],
```

### 2. Активируй валидацию в init.php

```php
$app = \BitrixCdd\Core\Application::getInstance();
$app->initialize();

$validation = $app->getService('validation');
$configService = $app->getService('iblock.config');

// Регистрируем правила для нужных инфоблоков
foreach (['news', 'team'] as $code) {
    $config = $configService->getConfig($code);
    if ($config) {
        $validation->registerFromConfig($code, $config);
    }
}

// Подключаем обработчики событий
$validation->initEventHandlers();
```

> **Важно**: валидация не включается автоматически при `initialize()`. Нужно явно вызвать `registerFromConfig()` и `initEventHandlers()`.

Готово. Теперь при сохранении элемента в админке, если заголовок длиннее 90 символов — пользователь увидит ошибку.

---

## Встроенные правила

### MaxLengthRule — максимальная длина строки

```php
'validation' => [
    'max_length' => 100,
]
```

- Перед проверкой удаляет HTML-теги (`strip_tags()`) и пробелы по краям (`trim()`)
- Подходит и для обычных строк, и для HTML-полей

Сообщение: `Заголовок не должен превышать 100 символов`

### MinValueRule — минимальное значение числа

```php
'validation' => [
    'min'           => 0,
    'min_inclusive'  => true,   // true = >= (по умолчанию), false = >
]
```

Примеры:
- `'min' => 0, 'min_inclusive' => true` → значение >= 0
- `'min' => 0, 'min_inclusive' => false` → значение > 0 (строго больше)

Сообщение: `Цена должна быть больше 0` или `Цена должна быть не менее 0`

### MaxCountRule — максимальное количество значений

```php
'validation' => [
    'max_count' => 5,
]
```

Для множественных свойств. Ограничивает количество элементов в массиве значений.

Сообщение: `Фотографии: максимум 5 элементов`

### RequiredFileRule — обязательный файл

Автоматически создаётся для свойств типа `F` с `'required' => true`:

```php
'COVER' => [
    'name' => 'Обложка',
    'type' => 'F',
    'required' => true,   // ← это всё что нужно
],
```

Проверяет наличие файла и при создании, и при обновлении элемента.

---

## Полный пример конфига

```php
'properties' => [
    'TITLE' => [
        'name' => 'Заголовок карточки',
        'type' => 'S',
        'required' => true,
        'validation' => [
            'max_length' => 90,
        ],
    ],
    'GOAL_AMOUNT' => [
        'name' => 'Цель сбора',
        'type' => 'N',
        'required' => true,
        'validation' => [
            'min' => 0,
            'min_inclusive' => false,  // строго больше нуля
        ],
    ],
    'COLLECTED_AMOUNT' => [
        'name' => 'Собрано',
        'type' => 'N',
        'validation' => [
            'min' => 0,
            'min_inclusive' => true,   // может быть нулём
        ],
    ],
    'PHOTOS' => [
        'name' => 'Фотографии',
        'type' => 'F',
        'multiple' => true,
        'validation' => [
            'max_count' => 10,
        ],
    ],
    'PREVIEW_IMAGE' => [
        'name' => 'Обложка',
        'type' => 'F',
        'required' => true,   // автоматически RequiredFileRule
    ],
],
```

---

## Создание своего правила

### Шаг 1: Класс правила

```php
<?php
namespace BitrixCdd\Validation\Rules;

use BitrixCdd\Validation\ValidationRule;

class EmailRule implements ValidationRule
{
    public function validate($value, array $context = []): bool
    {
        if ($value === null || $value === '') {
            return true;  // пустое — ок (для обязательности есть required)
        }
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function getErrorMessage(array $context = []): string
    {
        $fieldName = $context['field_name'] ?? 'Поле';
        return sprintf('%s должно содержать корректный email', $fieldName);
    }
}
```

Контекст `$context` содержит:
- `field_name` — человекочитаемое название поля (из конфига `name`)
- `property_code` — символьный код свойства
- `element_id` — ID элемента (при обновлении, `null` при создании)

### Шаг 2: Регистрация в PropertyValidator

Добавь обработку нового ключа в `PropertyValidator::addRulesFromConfig()`:

```php
if (isset($validation['email']) && $validation['email']) {
    $this->addRule($propertyCode, new EmailRule(), $fieldName);
}
```

### Шаг 3: Используй в конфиге

```php
'CONTACT_EMAIL' => [
    'name' => 'Email контакта',
    'type' => 'S',
    'validation' => [
        'email' => true,
    ],
],
```

---

## Программная работа

Кроме декларативной настройки через конфиги, валидатором можно управлять программно:

```php
use BitrixCdd\Validation\PropertyValidator;
use BitrixCdd\Validation\Rules\MaxLengthRule;

$validation = $app->getService('validation');

// Ручное создание валидатора
$validator = new PropertyValidator();
$validator->addRule('TITLE', new MaxLengthRule(100), 'Заголовок');
$validator->addRulesFromConfig('PRICE', [
    'name' => 'Цена',
    'type' => 'N',
    'validation' => ['min' => 0],
]);

// Регистрация для инфоблока
$validation->registerValidator('news', $validator);

// Подключение обработчиков
$validation->initEventHandlers();

// Получение валидатора
$v = $validation->getValidator('news');
```

---

## Как это работает под капотом

1. `ValidationService` регистрирует обработчики событий `OnBeforeIBlockElementAdd` и `OnBeforeIBlockElementUpdate`.
2. При сохранении элемента обработчик определяет инфоблок по `IBLOCK_ID`, находит его код.
3. Если для этого кода есть зарегистрированный валидатор — запускается проверка.
4. `PropertyValidator` проходит по всем свойствам и применяет правила.
5. Если есть ошибки — `$APPLICATION->ThrowException()` блокирует сохранение.

Все ошибки показываются одновременно — пользователь сразу видит все проблемы, а не по одной.
