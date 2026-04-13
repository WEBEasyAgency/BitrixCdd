<?php

namespace BitrixCdd\Infrastructure;

use BitrixCdd\Core\Application;

/**
 * Базовый класс для CDD-компонентов
 *
 * Не перехватывает executeComponent() -- разработчик пишет его как обычно.
 * Предоставляет хелперы для типовых операций:
 *
 *   class MyComponent extends BaseComponent {
 *       protected string $iblockCode = 'news';
 *
 *       public function executeComponent() {
 *           Loader::requireModule('iblock');
 *
 *           if ($this->startResultCache(3600)) {
 *               $this->arResult['ITEMS'] = $this->getItems(order: ['SORT' => 'ASC']);
 *               $this->registerCacheTags();
 *               $this->includeComponentTemplate();
 *           }
 *
 *           $this->showDebug();
 *       }
 *   }
 */
class BaseComponent extends \CBitrixComponent
{
    /** Код инфоблока. Задаётся в наследнике или через параметр IBLOCK_CODE */
    protected string $iblockCode = '';

    public function onPrepareComponentParams($arParams)
    {
        if (!empty($arParams['IBLOCK_CODE'])) {
            $this->iblockCode = $arParams['IBLOCK_CODE'];
        }
        $arParams['CACHE_TIME'] = (int)($arParams['CACHE_TIME'] ?? 3600);
        return $arParams;
    }

    /**
     * Вывод шаблона + автоматический debug для админов
     * Используется вместо $this->includeComponentTemplate()
     */
    protected function render(string $templatePage = ''): void
    {
        $this->includeComponentTemplate($templatePage);
        $this->showDebug();
    }

    /**
     * Получить элементы инфоблока через ElementDataExtractor
     */
    protected function getItems(array $filter = ['ACTIVE' => 'Y'], array $order = [], ?int $limit = null): array
    {
        $extractor = $this->getExtractor();
        if (!$extractor) {
            return [];
        }

        $params = [];
        if (!empty($order)) {
            $params['order'] = $order;
        }
        if ($limit !== null) {
            $params['limit'] = $limit;
        }

        return $extractor->getElements($this->iblockCode, $filter, $params);
    }

    /**
     * Получить ElementDataExtractor
     */
    protected function getExtractor(): ?ElementDataExtractor
    {
        return Application::getInstance()->getService('iblock.data_extractor');
    }

    /**
     * Получить IBlockConfigManager для текущего инфоблока
     */
    protected function getManager(): ?\BitrixCdd\Services\IBlockConfigManager
    {
        $configService = Application::getInstance()->getService('iblock.config');
        return $configService ? $configService->getManager($this->iblockCode) : null;
    }

    /**
     * Получить ID инфоблока
     */
    protected function getIblockId(): int
    {
        $manager = $this->getManager();
        return $manager ? $manager->getIBlockId() : 0;
    }

    /**
     * Регистрация тегов кеша по инфоблоку
     * Вызывать внутри startResultCache-блока
     */
    protected function registerCacheTags(): void
    {
        if (!defined('BX_COMP_MANAGED_CACHE')) {
            return;
        }

        $iblockId = $this->getIblockId();
        if ($iblockId > 0) {
            $taggedCache = \Bitrix\Main\Application::getInstance()->getTaggedCache();
            $taggedCache->registerTag('iblock_id_' . $iblockId);
        }
    }

    /**
     * DEBUG-вывод: только для авторизованных админов при DEBUG=Y
     * Вызывать в конце executeComponent()
     */
    protected function showDebug(): void
    {
        if (($this->arParams['DEBUG'] ?? 'N') !== 'Y') {
            return;
        }

        global $USER;
        if (!is_object($USER) || !$USER->IsAdmin()) {
            return;
        }

        $componentName = htmlspecialchars($this->getName());
        $iblockCode = htmlspecialchars($this->iblockCode);

        $resultJson = self::formatDebugData($this->arResult);
        $paramsJson = self::formatDebugData($this->arParams);

        echo <<<HTML
<details style="margin:12px 0;font-family:monospace;font-size:13px">
<summary style="cursor:pointer;padding:8px 12px;background:#1e1e2e;color:#cdd6f4;border-radius:6px 6px 0 0;user-select:none">
    <strong>{$componentName}</strong> <span style="color:#6c7086">iblock: {$iblockCode}</span>
</summary>
<div style="background:#1e1e2e;color:#cdd6f4;padding:12px 16px;border-radius:0 0 6px 6px;overflow-x:auto;max-height:600px;overflow-y:auto">
<div style="margin-bottom:8px;color:#f38ba8;font-weight:bold">\$arResult:</div>
<pre style="margin:0 0 16px;white-space:pre-wrap;word-break:break-word">{$resultJson}</pre>
<div style="margin-bottom:8px;color:#a6e3a1;font-weight:bold">\$arParams:</div>
<pre style="margin:0;white-space:pre-wrap;word-break:break-word">{$paramsJson}</pre>
</div>
</details>
HTML;
    }

    private static function formatDebugData(array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return htmlspecialchars($json ?: '{}');
    }
}
