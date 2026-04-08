<?php

namespace BitrixCdd\Core;

use BitrixCdd\Infrastructure\IBlockManager;
use BitrixCdd\Infrastructure\PropertyManager;
use BitrixCdd\Infrastructure\IBlockBuilderFactory;
use BitrixCdd\Infrastructure\IBlockTypeManager;
use BitrixCdd\Infrastructure\IBlockElementManager;
use BitrixCdd\Infrastructure\ElementDataExtractor;
use BitrixCdd\Infrastructure\VersionTracker;
use BitrixCdd\Infrastructure\TemplateManager;
use BitrixCdd\Infrastructure\HighloadBlockManager;
use BitrixCdd\Services\IBlockConfigService;
use BitrixCdd\Services\PropertyValueConverter;
use BitrixCdd\Services\HighloadBlockRegistrationService;
use BitrixCdd\Services\TemplateRegistrationService;
use BitrixCdd\Services\ValidationService;

/**
 * Класс приложения для управления сервисами (DI-контейнер)
 */
class Application
{
    /**
     * @var self|null Экземпляр приложения
     */
    private static ?self $instance = null;

    /**
     * @var array Зарегистрированные сервисы
     */
    private array $services = [];

    /**
     * @var string Директория с конфигурационными файлами
     */
    private string $configDir;

    /**
     * Приватный конструктор для реализации синглтона
     */
    private function __construct()
    {
    }

    /**
     * Получить экземпляр приложения
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Регистрация сервиса
     *
     * @param string $name Имя сервиса
     * @param object $service Объект сервиса
     */
    public function registerService(string $name, object $service): void
    {
        $this->services[$name] = $service;
    }

    /**
     * Получение сервиса
     *
     * @param string $name Имя сервиса
     * @return object|null
     */
    public function getService(string $name): ?object
    {
        return $this->services[$name] ?? null;
    }

    /**
     * Проверка существования сервиса
     *
     * @param string $name Имя сервиса
     * @return bool
     */
    public function hasService(string $name): bool
    {
        return isset($this->services[$name]);
    }

    /**
     * Удаление сервиса
     *
     * @param string $name Имя сервиса
     */
    public function removeService(string $name): void
    {
        unset($this->services[$name]);
    }

    /**
     * Получение всех зарегистрированных сервисов
     *
     * @return array
     */
    public function getAllServices(): array
    {
        return $this->services;
    }

    /**
     * Инициализация приложения и регистрация всех сервисов
     *
     * @param array $options Опции инициализации:
     *   - config_dir (string): Путь к директории конфигов (default: $_SERVER['DOCUMENT_ROOT'] . '/local/config')
     */
    public function initialize(array $options = []): void
    {
        $this->configDir = $options['config_dir']
            ?? $_SERVER['DOCUMENT_ROOT'] . '/local/config';
        $this->bootServices();
    }

    /**
     * Получить путь к директории конфигов
     */
    public function getConfigDir(): string
    {
        return $this->configDir;
    }

    /**
     * Загрузить и зарегистрировать все сервисы приложения
     */
    private function bootServices(): void
    {
        try {
            // 1. Infrastructure — менеджеры без зависимостей
            $iblockManager = new IBlockManager();
            $propertyManager = new PropertyManager();
            $typeManager = new IBlockTypeManager();
            $elementManager = new IBlockElementManager();
            $versionTracker = new VersionTracker();
            $templateManager = new TemplateManager();
            $hlBlockManager = new HighloadBlockManager();

            // 2. Фабрика builder-а (инжектирует менеджеры)
            $builderFactory = new IBlockBuilderFactory($iblockManager, $propertyManager);

            // 3. Утилитные сервисы
            $valueConverter = new PropertyValueConverter();

            // 4. CDD система регистрации из конфигурации
            $configService = new IBlockConfigService(
                $elementManager,
                $builderFactory,
                $typeManager,
                $versionTracker,
                $valueConverter,
                $this->configDir
            );
            $this->registerService('iblock.config', $configService);
            $configService->registerAll();

            // 5. Извлечение данных элементов согласно конфигурации
            $dataExtractor = new ElementDataExtractor($configService);
            $this->registerService('iblock.data_extractor', $dataExtractor);

            // 6. Highload-блоки
            $hlRegistration = new HighloadBlockRegistrationService($hlBlockManager);
            $this->registerService('hlblock.registration', $hlRegistration);

            // 7. Шаблоны
            $templateRegistration = new TemplateRegistrationService($templateManager);
            $this->registerService('template.registration', $templateRegistration);

            // 8. Валидация
            $validation = new ValidationService();
            $this->registerService('validation', $validation);
        } catch (\Exception $e) {
            error_log('BitrixCdd Application::bootServices() error: ' . $e->getMessage());
        }
    }

    /**
     * Предотвращение клонирования
     */
    private function __clone()
    {
    }

    /**
     * Предотвращение десериализации
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
