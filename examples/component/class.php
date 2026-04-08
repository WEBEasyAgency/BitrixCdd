<?php
/**
 * Пример классового компонента Bitrix с использованием BitrixCdd
 *
 * Структура файлов компонента:
 *   local/components/easy/news.list/
 *   ├── class.php                      ← этот файл
 *   ├── .description.php               (опционально — описание для админки)
 *   └── templates/
 *       └── .default/
 *           └── template.php           ← шаблон вывода
 *
 * Подключение на странице:
 *   $APPLICATION->IncludeComponent('easy:news.list', '', [
 *       'CACHE_TIME' => 3600,
 *       // 'DEBUG' => 'Y',  // раскомментируй для отладки
 *   ]);
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use BitrixCdd\Core\Application;
use BitrixCdd\Infrastructure\BaseComponent;

class NewsListComponent extends BaseComponent
{
    public function executeComponent()
    {
        Loader::requireModule('iblock');

        // Кэширование (стандартный механизм Bitrix)
        if ($this->startResultCache($this->arParams['CACHE_TIME'] ?? 3600)) {

            $this->fetchData();

            // Если элементов нет — не кэшируем пустоту
            if (empty($this->arResult['ITEMS'])) {
                $this->abortResultCache();
                return;
            }

            // Тегированный кэш — сбросится при изменении элементов инфоблока
            if (defined('BX_COMP_MANAGED_CACHE')) {
                $taggedCache = \Bitrix\Main\Application::getInstance()->getTaggedCache();
                $taggedCache->registerTag('iblock_id_' . $this->getIblockId());
            }

            // Подключаем шаблон
            $this->includeComponentTemplate();
        }

        // Режим отладки: выводит $arResult если передан параметр DEBUG=Y
        if ($this->arParams['DEBUG'] === 'Y') {
            echo '<pre>' . var_export($this->arResult, true) . '</pre>';
        }
    }

    /**
     * Получение данных через ElementDataExtractor
     *
     * Extractor автоматически:
     * - конвертирует ID файлов в URL
     * - enum ID в текстовые значения
     * - HTML-массивы в строки
     * - и т.д. — согласно секции 'fields' в конфиге инфоблока
     */
    private function fetchData(): void
    {
        $app = Application::getInstance();

        /** @var \BitrixCdd\Infrastructure\ElementDataExtractor $extractor */
        $extractor = $app->getService('iblock.data_extractor');

        $this->arResult['ITEMS'] = $extractor->getElements(
            'cdd_full_test',                  // код инфоблока из конфига
            ['ACTIVE' => 'Y'],                // фильтр
            ['order' => ['SORT' => 'ASC']]    // параметры
        );
    }

    /**
     * ID инфоблока (для тегированного кэша)
     */
    private function getIblockId(): int
    {
        $app = Application::getInstance();
        $configService = $app->getService('iblock.config');
        $manager = $configService->getManager('cdd_full_test');

        return $manager ? $manager->getIBlockId() : 0;
    }
}
