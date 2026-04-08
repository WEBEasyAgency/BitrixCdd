<?php

namespace BitrixCdd\Infrastructure;

use Bitrix\Main\Loader;
use Bitrix\Main\SiteTable;
use BitrixCdd\Domain\Contracts\TemplateRegistrarInterface;
use BitrixCdd\Domain\Entities\TemplateEntity;

/**
 * Менеджер шаблонов
 * Реализация программной регистрации шаблонов через API Bitrix
 */
class TemplateManager implements TemplateRegistrarInterface
{
    /**
     * Регистрация шаблона для определенного пути
     *
     * @param TemplateEntity $template Сущность шаблона
     * @return bool
     */
    public function register(TemplateEntity $template): bool
    {
        if (!Loader::includeModule('main')) {
            throw new \Exception('Модуль main не доступен');
        }

        $siteId = $template->getSiteId();

        $arSite = SiteTable::getById($siteId)->fetch();
        if (!$arSite) {
            return false;
        }

        // Получаем существующие шаблоны
        $existingTemplates = $this->getTemplatesForSite($siteId);

        $newCondition = $template->getPath() === '/' ? '' : "CSite::InDir('" . $template->getPath() . "')";
        $newEntry = [
            'CONDITION' => $newCondition,
            'SORT' => $template->getSort(),
            'TEMPLATE' => $template->getTemplate(),
        ];

        // Обновляем существующий шаблон с таким же условием или добавляем новый
        $found = false;
        foreach ($existingTemplates as $i => $existing) {
            if (($existing['CONDITION'] ?? '') === $newCondition) {
                $existingTemplates[$i] = $newEntry;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $existingTemplates[] = $newEntry;
        }

        // Сортируем по SORT
        usort($existingTemplates, fn($a, $b) => ($a['SORT'] ?? 100) <=> ($b['SORT'] ?? 100));

        $obSite = new \CSite();
        $result = $obSite->Update($siteId, ['TEMPLATE' => $existingTemplates]);

        return $result ? true : false;
    }

    /**
     * Получение шаблона для пути
     *
     * @param string $path Путь
     * @return string|null Путь к шаблону или null
     */
    public function getTemplateForPath(string $path): ?string
    {
        if (!Loader::includeModule('main')) {
            return null;
        }

        $templates = $this->getTemplatesForSite('s1');
        if (!empty($templates)) {
            return $templates[0]['TEMPLATE'] ?? null;
        }

        return null;
    }

    /**
     * Удаление регистрации шаблона
     *
     * @param string $path Путь
     * @return bool
     */
    public function unregister(string $path): bool
    {
        if (!Loader::includeModule('main')) {
            return false;
        }

        $siteId = 's1';
        $arSite = SiteTable::getById($siteId)->fetch();
        if (!$arSite) {
            return false;
        }

        $arFields = [
            'TEMPLATE' => [
                [
                    'CONDITION' => '',
                    'SORT' => 100,
                    'TEMPLATE' => '.default',
                ],
            ],
        ];

        $obSite = new \CSite();
        $result = $obSite->Update($siteId, $arFields);

        return $result ? true : false;
    }

    /**
     * Проверка существования шаблона для пути
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return $this->getTemplateForPath($path) !== null;
    }

    /**
     * Получение всех зарегистрированных шаблонов
     *
     * @return array
     */
    public function getAllTemplates(): array
    {
        if (!Loader::includeModule('main')) {
            return [];
        }

        return $this->getTemplatesForSite('s1');
    }

    /**
     * Получить шаблоны для сайта
     * CSite::GetByID используется т.к. SiteTable D7 не возвращает TEMPLATE
     * (шаблоны хранятся в b_site_template, нет D7 DataManager)
     *
     * @param string $siteId
     * @return array
     */
    private function getTemplatesForSite(string $siteId): array
    {
        $rsSites = \CSite::GetByID($siteId);
        if ($arSite = $rsSites->Fetch()) {
            if (isset($arSite['TEMPLATE']) && is_array($arSite['TEMPLATE'])) {
                return $arSite['TEMPLATE'];
            }
        }

        return [];
    }
}
