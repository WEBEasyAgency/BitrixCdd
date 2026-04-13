<?php

namespace BitrixCdd\Services;

/**
 * Типограф для русского текста
 *
 * Обрабатывает типографские правила:
 * - Неразрывные пробелы после предлогов и союзов
 *
 * Использование:
 *   $typographer = new Typographer();
 *   $text = $typographer->process('Мы работаем в Москве и в Питере');
 *   // "Мы работаем в&nbsp;Москве и&nbsp;в&nbsp;Питере"
 */
class Typographer
{
    /** Предлоги и союзы, после которых ставится неразрывный пробел */
    private const PREPOSITIONS = [
        'в', 'во', 'на', 'за', 'из', 'до', 'по', 'об', 'от', 'к', 'с', 'у', 'о',
        'и', 'а', 'но', 'не', 'ни', 'же', 'бы', 'ли',
        'со', 'ко', 'без', 'при', 'для', 'под', 'над', 'про', 'через',
    ];

    /** Скомпилированное регулярное выражение */
    private string $pattern;

    public function __construct()
    {
        $words = implode('|', self::PREPOSITIONS);
        // Ищем предлог как отдельное слово + пробел после него
        // (?<=\s|^|>) -- перед предлогом: пробел, начало строки или закрывающий тег
        // ({words}) -- сам предлог (case-insensitive)
        // \s+ -- один или несколько пробелов после предлога
        $this->pattern = '/(?<=\s|^|>)(' . $words . ')\s+/iu';
    }

    /**
     * Обработать текст: расставить неразрывные пробелы после предлогов
     */
    public function process(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        // Для HTML-контента обрабатываем только текст вне тегов
        if (str_contains($text, '<')) {
            return $this->processHtml($text);
        }

        return preg_replace($this->pattern, '$1&nbsp;', $text);
    }

    /**
     * Обработать HTML: заменяем только в текстовых узлах, теги не трогаем
     */
    private function processHtml(string $html): string
    {
        // Разбиваем на теги и текст между ними
        // Шаблон захватывает HTML-теги, HTML-entities и текст
        return preg_replace_callback(
            '/(<[^>]+>|&[a-zA-Z]+;|&#\d+;)|([^<&]+)/',
            function ($matches) {
                // $matches[1] -- тег или entity, не трогаем
                if (!empty($matches[1])) {
                    return $matches[1];
                }
                // $matches[2] -- текст, обрабатываем
                return preg_replace($this->pattern, '$1&nbsp;', $matches[2]);
            },
            $html
        );
    }
}
