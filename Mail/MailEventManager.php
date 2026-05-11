<?php

namespace BitrixCdd\Mail;

use Bitrix\Main\EventManager;
use CEventType;
use CEventMessage;
use CEvent;

/**
 * Конфигурируемая система почтовых событий для Bitrix
 *
 * СТРУКТУРА:
 * - Код: local/classes/BitrixCdd/Mail/MailEventManager.php
 * - Конфиг: local/config/mail/config.php
 * - Шаблоны: local/config/mail/templates/
 *
 * ПЕРЕНОС В ДРУГОЙ ПРОЕКТ:
 * 1. Скопировать local/classes/BitrixCdd/ в local/classes/
 * 2. Скопировать local/config/mail/ в local/config/
 * 3. Добавить в init.php: \BitrixCdd\Mail\MailEventManager::init();
 *
 * @example
 * // Ручная отправка события
 * \BitrixCdd\Mail\MailEventManager::send('therapist_activation', [
 *     'NAME' => 'Иван',
 *     'EMAIL' => 'ivan@example.com',
 * ]);
 */
class MailEventManager
{
    private static ?array $config = null;
    private static bool $initialized = false;
    private static array $userFieldsCache = [];
    private static bool $debug = true;

    /**
     * Логирование для отладки
     */
    private static function log(string $message, array $context = []): void
    {
        if (!self::$debug) {
            return;
        }

        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/mail_events.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        file_put_contents($logFile, "[{$timestamp}] {$message}{$contextStr}\n", FILE_APPEND);
    }

    /**
     * Инициализация системы
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::loadConfig();
        self::registerMailEvents();
        self::registerTriggers();

        self::$initialized = true;
    }

    /**
     * Загружает конфигурацию из local/config/mail/config.php
     */
    private static function loadConfig(): void
    {
        $configPath = $_SERVER['DOCUMENT_ROOT'] . '/local/config/mail/config.php';

        if (!file_exists($configPath)) {
            self::$config = ['events' => []];
            return;
        }

        self::$config = require $configPath;
    }

    /**
     * Возвращает конфигурацию
     */
    public static function getConfig(): array
    {
        if (self::$config === null) {
            self::loadConfig();
        }
        return self::$config;
    }

    /**
     * Регистрирует почтовые события в Bitrix
     */
    private static function registerMailEvents(): void
    {
        foreach (self::getConfig()['events'] as $eventKey => $eventConfig) {
            self::registerSingleEvent($eventConfig);
        }
    }

    /**
     * Регистрирует одно почтовое событие
     */
    private static function registerSingleEvent(array $eventConfig): void
    {
        $eventCode = $eventConfig['code'] ?? '';
        if (empty($eventCode)) {
            return;
        }

        // Проверяем существование типа события
        $event = CEventType::GetList(['EVENT_NAME' => $eventCode])->Fetch();

        if (!$event) {
            $description = $eventConfig['description'] ?? '';
            $fields = $eventConfig['fields'] ?? [];

            if (!empty($fields)) {
                $description .= "\n\nДоступные поля:\n";
                foreach ($fields as $code => $name) {
                    $description .= "#{$code}# - {$name}\n";
                }
            }

            $eventType = new CEventType;
            $eventType->Add([
                'LID' => 'ru',
                'EVENT_NAME' => $eventCode,
                'NAME' => $eventConfig['name'] ?? $eventCode,
                'DESCRIPTION' => $description,
            ]);
        }

        // Проверяем версию шаблона (храним в своём файле, не в Bitrix)
        $configVersion = $eventConfig['template']['version'] ?? '1';
        $installedVersion = self::getInstalledVersion($eventCode);

        if (version_compare($configVersion, $installedVersion, '>')) {
            // Версия в конфиге выше - создаём или обновляем шаблон
            self::installTemplate($eventConfig);
            self::setInstalledVersion($eventCode, $configVersion);
        }
    }

    /**
     * Получает установленную версию шаблона из файла
     */
    private static function getInstalledVersion(string $eventCode): string
    {
        $versionsFile = $_SERVER['DOCUMENT_ROOT'] . '/local/config/mail/.versions.json';
        if (!file_exists($versionsFile)) {
            return '0';
        }
        $versions = json_decode(file_get_contents($versionsFile), true) ?: [];
        return $versions[$eventCode] ?? '0';
    }

    /**
     * Сохраняет установленную версию шаблона в файл
     */
    private static function setInstalledVersion(string $eventCode, string $version): void
    {
        $versionsFile = $_SERVER['DOCUMENT_ROOT'] . '/local/config/mail/.versions.json';
        $versions = [];
        if (file_exists($versionsFile)) {
            $versions = json_decode(file_get_contents($versionsFile), true) ?: [];
        }
        $versions[$eventCode] = $version;
        file_put_contents($versionsFile, json_encode($versions, JSON_PRETTY_PRINT));
    }

    /**
     * Устанавливает шаблон (создаёт или обновляет)
     */
    private static function installTemplate(array $eventConfig): void
    {
        $eventCode = $eventConfig['code'];
        $templateConfig = $eventConfig['template'] ?? [];
        $templateFile = $templateConfig['file'] ?? '';

        // Читаем HTML из файла
        $message = '';
        if (!empty($templateFile)) {
            $templatePath = $_SERVER['DOCUMENT_ROOT'] . '/local/config/mail/templates/' . $templateFile;
            if (file_exists($templatePath)) {
                $message = file_get_contents($templatePath);
            }
        }
        if (empty($message)) {
            $message = '<p>Шаблон не настроен</p>';
        }

        // Удаляем старые шаблоны для этого события (прямой SQL, т.к. CEventMessage::GetList глючит)
        $conn = \Bitrix\Main\Application::getConnection();
        $conn->queryExecute("DELETE FROM b_event_message WHERE EVENT_NAME = '" . $conn->getSqlHelper()->forSql($eventCode) . "'");

        // Создаём новый шаблон
        $eventMessage = new CEventMessage;
        $eventMessage->Add([
            'ACTIVE' => 'Y',
            'EVENT_NAME' => $eventCode,
            'LID' => self::getDefaultSiteId(),
            'EMAIL_FROM' => $templateConfig['from'] ?? '#DEFAULT_EMAIL_FROM#',
            'EMAIL_TO' => $templateConfig['to'] ?? '#EMAIL#',
            'SUBJECT' => $templateConfig['subject'] ?? 'Уведомление',
            'BODY_TYPE' => 'html',
            'MESSAGE' => $message,
        ]);

        self::log('installTemplate: installed', ['code' => $eventCode, 'version' => $templateConfig['version'] ?? '1']);
    }

    /**
     * Регистрирует триггеры Bitrix-событий (persistent в БД)
     */
    private static function registerTriggers(): void
    {
        $eventManager = EventManager::getInstance();
        $handlers = [];

        foreach (self::getConfig()['events'] as $eventConfig) {
            foreach ($eventConfig['triggers'] ?? [] as $trigger) {
                $module = $trigger['module'] ?? 'main';
                $event = $trigger['event'] ?? '';

                if (empty($event)) {
                    continue;
                }

                // OnBeforeUserUpdate нужен для захвата предыдущих значений
                if ($event === 'OnAfterUserUpdate') {
                    $handlers["{$module}:OnBeforeUserUpdate"] = ['module' => $module, 'event' => 'OnBeforeUserUpdate'];
                }

                $handlers["{$module}:{$event}"] = ['module' => $module, 'event' => $event];
            }
        }

        foreach ($handlers as $key => $handler) {
            $method = match ($handler['event']) {
                'OnBeforeUserUpdate' => 'onBeforeUserUpdate',
                'OnAfterUserUpdate' => 'onAfterUserUpdate',
                default => null,
            };

            if (!$method) {
                continue;
            }

            // Проверяем, не зарегистрирован ли уже обработчик в БД
            $existingHandlers = $eventManager->findEventHandlers($handler['module'], $handler['event']);
            $alreadyRegistered = false;

            foreach ($existingHandlers as $existing) {
                if (
                    isset($existing['TO_CLASS']) &&
                    $existing['TO_CLASS'] === '\\BitrixCdd\\Mail\\MailEventManager' &&
                    $existing['TO_METHOD'] === $method
                ) {
                    $alreadyRegistered = true;
                    break;
                }
            }

            if (!$alreadyRegistered) {
                // Регистрируем в БД (persistent) - работает и в админке
                // Указываем путь к файлу для автозагрузки класса
                $eventManager->registerEventHandler(
                    $handler['module'],
                    $handler['event'],
                    'main',
                    '\\BitrixCdd\\Mail\\MailEventManager',
                    $method,
                    100,
                    '/local/classes/BitrixCdd/Mail/MailEventManager.php'
                );
            }
        }
    }

    /**
     * Захватывает предыдущие значения полей (OnBeforeUserUpdate)
     */
    public static function onBeforeUserUpdate(array &$arFields): void
    {
        $userId = $arFields['ID'] ?? null;
        self::log('onBeforeUserUpdate called', ['userId' => $userId, 'fields_keys' => array_keys($arFields)]);

        if (!$userId) {
            self::log('onBeforeUserUpdate: no userId, skipping');
            return;
        }

        try {
            $user = \Bitrix\Main\UserTable::getById($userId)->fetch();
            if ($user) {
                self::$userFieldsCache[$userId] = $user;
                self::log('onBeforeUserUpdate: cached user data', [
                    'userId' => $userId,
                    'ACTIVE' => $user['ACTIVE'] ?? 'NOT SET'
                ]);
            }
        } catch (\Exception $e) {
            self::log('onBeforeUserUpdate: exception', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Обработчик OnAfterUserUpdate
     */
    public static function onAfterUserUpdate(array &$arFields): void
    {
        $userId = $arFields['ID'] ?? null;
        self::log('onAfterUserUpdate called', [
            'userId' => $userId,
            'ACTIVE' => $arFields['ACTIVE'] ?? 'NOT IN FIELDS',
            'fields_keys' => array_keys($arFields)
        ]);
        self::processTriggers('main', 'OnAfterUserUpdate', $arFields);
    }

    /**
     * Обрабатывает триггеры для события
     */
    private static function processTriggers(string $module, string $eventName, array $params): void
    {
        // Загружаем конфиг если не загружен
        if (self::$config === null) {
            self::loadConfig();
        }

        $events = self::getConfig()['events'] ?? [];

        foreach ($events as $eventKey => $eventConfig) {
            foreach ($eventConfig['triggers'] ?? [] as $trigger) {
                if (($trigger['module'] ?? 'main') !== $module || ($trigger['event'] ?? '') !== $eventName) {
                    continue;
                }

                if (!self::checkConditions($trigger, $params)) {
                    continue;
                }

                $fields = self::mapFields($trigger, $params);
                self::sendByCode($eventConfig['code'], $fields);
            }
        }
    }

    /**
     * Проверяет условия триггера
     */
    private static function checkConditions(array $trigger, array $arFields): bool
    {
        $conditions = $trigger['conditions'] ?? [];
        $userId = $arFields['ID'] ?? null;

        self::log('checkConditions start', ['userId' => $userId, 'conditions' => $conditions]);

        if (empty($conditions)) {
            self::log('checkConditions: no conditions, returning true');
            return true;
        }

        // field_changed: ['ACTIVE', 'N', 'Y']
        if (isset($conditions['field_changed'])) {
            [$fieldName, $oldValue, $newValue] = $conditions['field_changed'];

            $previousData = self::$userFieldsCache[$userId] ?? [];
            $oldFieldValue = $previousData[$fieldName] ?? null;
            $newFieldValue = $arFields[$fieldName] ?? null;

            self::log('checkConditions: field_changed check', [
                'fieldName' => $fieldName,
                'expectedOld' => $oldValue,
                'expectedNew' => $newValue,
                'actualOld' => $oldFieldValue,
                'actualNew' => $newFieldValue,
                'cacheExists' => !empty($previousData)
            ]);

            // Очищаем кеш
            if ($userId) {
                unset(self::$userFieldsCache[$userId]);
            }

            if ($oldFieldValue !== $oldValue || $newFieldValue !== $newValue) {
                self::log('checkConditions: field_changed FAILED');
                return false;
            }
            self::log('checkConditions: field_changed PASSED');
        }

        // user_groups: ['therapist', 'psychologist', ...]
        if (isset($conditions['user_groups'])) {
            $inGroups = $userId && self::userInGroups((int)$userId, $conditions['user_groups']);
            self::log('checkConditions: user_groups check', [
                'requiredGroups' => $conditions['user_groups'],
                'result' => $inGroups
            ]);
            if (!$inGroups) {
                self::log('checkConditions: user_groups FAILED');
                return false;
            }
            self::log('checkConditions: user_groups PASSED');
        }

        self::log('checkConditions: ALL PASSED');
        return true;
    }

    /**
     * Проверяет принадлежность пользователя к группам
     */
    private static function userInGroups(int $userId, array $groupCodes): bool
    {
        try {
            $userGroups = \CUser::GetUserGroup($userId);
            $groupResult = \Bitrix\Main\GroupTable::getList([
                'filter' => ['@ID' => $userGroups],
                'select' => ['STRING_ID'],
            ]);

            while ($group = $groupResult->fetch()) {
                if (in_array($group['STRING_ID'], $groupCodes)) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Маппит поля
     */
    private static function mapFields(array $trigger, array $arFields): array
    {
        $mapping = $trigger['fields_map'] ?? [];
        $result = [];

        $userId = $arFields['ID'] ?? null;
        $userData = [];
        if ($userId) {
            try {
                $userData = \Bitrix\Main\UserTable::getById($userId)->fetch() ?: [];
            } catch (\Exception $e) {
                $userData = [];
            }
        }

        foreach ($mapping as $mailField => $source) {
            if (str_starts_with($source, '@')) {
                $result[$mailField] = self::getSpecialValue($source);
            } elseif (str_starts_with($source, 'user.')) {
                $result[$mailField] = $userData[substr($source, 5)] ?? '';
            } else {
                $result[$mailField] = $arFields[$source] ?? '';
            }
        }

        return $result;
    }

    /**
     * Специальные значения
     */
    private static function getSpecialValue(string $key): string
    {
        switch ($key) {
            case '@site_url':
                try {
                    $request = \Bitrix\Main\Context::getCurrent()->getRequest();
                    return ($request->isHttps() ? 'https' : 'http') . '://' . $request->getHttpHost();
                } catch (\Exception $e) {
                    return '/';
                }

            case '@site_name':
                try {
                    $siteId = self::getDefaultSiteId();
                    $site = \Bitrix\Main\SiteTable::getList(['filter' => ['=LID' => $siteId], 'select' => ['NAME']])->fetch();
                    return $site['NAME'] ?? 'Сайт';
                } catch (\Exception $e) {
                    return 'Сайт';
                }

            default:
                return '';
        }
    }

    /**
     * Возвращает ID сайта по умолчанию
     */
    private static function getDefaultSiteId(): string
    {
        // Получаем сайт по умолчанию из БД
        $site = \Bitrix\Main\SiteTable::getList([
            'filter' => ['=DEF' => 'Y', '=ACTIVE' => 'Y'],
            'select' => ['LID'],
            'limit' => 1,
        ])->fetch();

        return $site['LID'] ?? 's1';
    }

    /**
     * Отправляет событие по коду (немедленно)
     */
    public static function sendByCode(string $eventCode, array $fields): bool
    {
        $siteId = self::getDefaultSiteId();
        self::log('sendByCode called', ['eventCode' => $eventCode, 'fields' => $fields, 'siteId' => $siteId]);

        $result = CEvent::Send($eventCode, $siteId, $fields, 'N');

        self::log('sendByCode result', ['eventCode' => $eventCode, 'result' => $result]);

        // Немедленная отправка из очереди
        if ($result) {
            CEvent::CheckEvents();
            self::log('sendByCode: CheckEvents called');
        }

        return (bool)$result;
    }

    /**
     * Отправляет событие по ключу из конфига
     *
     * @param string $eventKey Ключ события (например, 'therapist_activation')
     * @param array $fields Поля для шаблона
     */
    public static function send(string $eventKey, array $fields): bool
    {
        $config = self::getConfig();
        $eventConfig = $config['events'][$eventKey] ?? null;

        if (!$eventConfig || empty($eventConfig['code'])) {
            return false;
        }

        return self::sendByCode($eventConfig['code'], $fields);
    }
}
