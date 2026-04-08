# Валидация свойств

Система валидации проверяет значения свойств при сохранении элементов через админку Bitrix. Если правила нарушены — элемент не сохранится и пользователь увидит сообщение об ошибке.

## Настройка в конфиге

Правила задаются в ключе `validation` свойства:

```php
'properties' => [
    'TITLE' => [
        'name' => 'Заголовок',
        'type' => 'S',
        'validation' => [
            'max_length' => 90,   // Макс. длина (HTML-теги удаляются перед проверкой)
        ],
    ],
    'PRICE' => [
        'name' => 'Цена',
        'type' => 'N',
        'validation' => [
            'min'           => 0,      // Минимальное значение
            'min_inclusive'  => false,  // false = строго больше (>), true = >= (по умолчанию true)
        ],
    ],
    'PHOTOS' => [
        'name' => 'Фотографии',
        'type' => 'F',
        'multiple' => true,
        'validation' => [
            'max_count' => 5,   // Макс. 5 файлов
        ],
    ],
    'COVER' => [
        'name' => 'Обложка',
        'type' => 'F',
        'required' => true,    // Для type=F автоматически создаётся RequiredFileRule
    ],
],
```

## Встроенные правила

| Правило | Ключ конфигурации | Применяется к | Описание |
|---------|-------------------|---------------|----------|
| `MaxLengthRule` | `max_length: N` | Строки (S) | Максимальная длина после `strip_tags()` + `trim()` |
| `MinValueRule` | `min: N`, `min_inclusive: bool` | Числа (N) | Минимальное значение (включительно или строго) |
| `MaxCountRule` | `max_count: N` | Множественные | Максимум N значений |
| `RequiredFileRule` | `required: true` при `type: 'F'` | Файлы (F) | Файл обязателен при создании и обновлении |

## Активация валидации

Валидация **не активируется автоматически** при `initialize()`. Её нужно подключить явно в `init.php`:

```php
$app = Application::getInstance();
$app->initialize();

// Подключаем валидацию
$validation = $app->getService('validation');
$configService = $app->getService('iblock.config');

// Регистрируем правила из конфигов
foreach (['news', 'articles'] as $code) {  // перечисли свои инфоблоки
    $config = $configService->getConfig($code);
    if ($config) {
        $validation->registerFromConfig($code, $config);
    }
}

// Активируем обработчики событий Bitrix
$validation->initEventHandlers();
```

После этого валидация будет срабатывать при сохранении элементов через админку.

## Сообщения об ошибках

При нарушении правил элемент не сохраняется, и пользователь видит:
```
Заголовок не должен превышать 90 символов
Цена должна быть больше 0
Фотографии: максимум 5 элементов
```

## Своё правило валидации

1. Создай класс, реализующий `ValidationRule`:

```php
<?php
namespace BitrixCdd\Validation\Rules;

use BitrixCdd\Validation\ValidationRule;

class EmailRule implements ValidationRule
{
    public function validate($value, array $context = []): bool
    {
        if ($value === null || $value === '') {
            return true;  // пустое значение — ок (для обязательности есть required)
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

2. Зарегистрируй обработку нового ключа в `PropertyValidator::addRulesFromConfig()`:

```php
if (isset($validation['email']) && $validation['email']) {
    $this->addRule($propertyCode, new EmailRule(), $fieldName);
}
```

3. Используй в конфиге:

```php
'EMAIL' => [
    'name' => 'Email',
    'type' => 'S',
    'validation' => ['email' => true],
],
```
