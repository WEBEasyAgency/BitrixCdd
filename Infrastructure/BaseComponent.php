<?php

namespace BitrixCdd\Infrastructure;

/**
 * Базовый класс для компонентов Bitrix
 *
 * Наследуется от CBitrixComponent и добавляет:
 * - Поддержку параметра DEBUG для вывода данных компонента
 * - Единый интерфейс для всех компонентов
 *
 * Использование в наследующем классе:
 * class MyComponent extends BaseComponent {
 *     public function executeComponent() {
 *         // Работа с $this->arResult
 *         $this->arResult['ITEMS'] = [...];
 *
 *         // Вызов родительского метода для вывода шаблона и отладки
 *         parent::executeComponent();
 *     }
 * }
 */
class BaseComponent extends \CBitrixComponent
{
    /**
     * Выполнить компонент
     *
     * Вызовите этот метод в конце вашего executeComponent() чтобы:
     * 1. Вывести шаблон компонента
     * 2. Показать отладочную информацию если DEBUG=Y
     */
    public function executeComponent()
    {
        $this->includeComponentTemplate();

        if ($this->arParams['DEBUG'] === 'Y') {
            $this->showDebug();
        }
    }

    /**
     * Вывести отладочную информацию
     * Показывает содержимое $arResult в теге <pre>
     */
    private function showDebug(): void
    {
        echo "\n<!-- DEBUG OUTPUT: {$this->getName()} -->\n";
        echo "<pre style=\"background: #f5f5f5; padding: 15px; margin: 20px; border: 2px solid #e74c3c; border-radius: 5px; font-size: 12px; overflow-x: auto;\">\n";
        echo "<strong>Component Debug: " . htmlspecialchars($this->getName()) . "</strong>\n\n";
        echo "<strong>\$arResult:</strong>\n";
        echo var_export($this->arResult, true);
        echo "\n\n<strong>\$arParams:</strong>\n";
        echo var_export($this->arParams, true);
        echo "\n</pre>\n";
        echo "<!-- END DEBUG OUTPUT -->\n\n";
    }
}
