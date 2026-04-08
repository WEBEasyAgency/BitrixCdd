# Демо-данные

Демо-данные создаются при инициализации. Поведение зависит от `need_sync` и `strict` (см. [versioning.md](versioning.md)).

## Формат

```php
'demo_data' => [
    [
        'code'            => 'first-news',                // Обязательно. Символьный код
        'name'            => 'Первая новость',            // Обязательно. Название
        'active'          => 'Y',                         // По умолчанию 'Y'
        'sort'            => 500,                         // По умолчанию 500
        'preview_text'    => 'Краткий текст',
        'detail_text'     => '<p>Полный текст</p>',
        'preview_picture' => '/local/images/news1.jpg',   // Путь от DOCUMENT_ROOT
        'detail_picture'  => '/local/images/news1_big.jpg',

        'properties' => [
            // Строка → передаётся как есть
            'AUTHOR' => 'Admin',

            // Число → передаётся как есть
            'VIEWS' => 42,

            // Список (L) → передаёшь ТЕКСТ значения, а не ID
            'TAGS' => ['Важное', 'Черновик'],

            // Файл (F) → путь от DOCUMENT_ROOT
            'IMAGE' => '/local/images/pic.jpg',

            // Множественные файлы
            'GALLERY' => [
                '/local/images/pic1.jpg',
                '/local/images/pic2.jpg',
            ],

            // Привязка к элементу (E) → символьный код или числовой ID
            'LINKED' => 'article-1',        // по CODE
            'LINKED2' => 42,                // по ID

            // Дата (S + Date) → формат YYYY-MM-DD (автоматически → DD.MM.YYYY)
            'DATE_START' => '2026-01-15',

            // Дата-время (S + DateTime) → YYYY-MM-DD HH:MM:SS
            'PUBLISHED_AT' => '2026-01-15 14:30:00',

            // HTML (S + HTML) → строка (автоматически → ['VALUE' => ['TEXT' => ..., 'TYPE' => 'html']])
            'BODY' => '<p>HTML контент</p>',

            // HTML можно и в явном формате
            'BODY2' => ['VALUE' => '<p>HTML</p>', 'TYPE' => 'HTML'],
        ],
    ],
],
```

## Автоматическая конвертация значений

Библиотека сама преобразует удобный формат конфига в формат Bitrix API:

| Тип свойства | В конфиге пишешь | Что получает Bitrix |
|-------------|-----------------|---------------------|
| `L` (список) | `'Важное'` (текст) | ID enum-значения |
| `F` (файл) | `'/local/images/pic.jpg'` | Массив `CFile::MakeFileArray` |
| `E` (элемент) | `'article-1'` (код) или `42` (ID) | ID элемента |
| `G` (раздел) | `42` (ID) | ID раздела |
| `S` + `Date` | `'2026-01-15'` | `'15.01.2026'` |
| `S` + `DateTime` | `'2026-01-15 14:30:00'` | `'15.01.2026 14:30:00'` |
| `S` + `HTML` | `'<p>Текст</p>'` | `['VALUE' => ['TEXT' => ..., 'TYPE' => 'html']]` |
