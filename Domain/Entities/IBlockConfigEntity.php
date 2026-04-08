<?php

namespace BitrixCdd\Domain\Entities;

/**
 * Сущность конфигурации инфоблока
 * Представляет декларативное описание инфоблока, его свойств и демо-данных
 */
class IBlockConfigEntity
{
    /**
     * @param array $iblock Конфигурация инфоблока (type, code, name, site_id, sort)
     * @param array $properties Массив свойств инфоблока [code => config]
     * @param array $demoData Массив демо-элементов
     * @param string|null $version Версия конфигурации для отслеживания изменений
     * @param bool $needSync Нужно ли синхронизировать инфоблок и поля
     * @param bool $strict Жёсткая синхронизация - удалять все элементы не из конфига
     */
    public function __construct(
        private array $iblock,
        private array $properties = [],
        private array $demoData = [],
        private ?string $version = null,
        private bool $needSync = false,
        private bool $strict = false
    ) {
        $this->validate();
    }

    /**
     * Создать из массива конфигурации
     */
    public static function fromArray(array $config): self
    {
        return new self(
            iblock: $config['iblock'] ?? [],
            properties: $config['properties'] ?? [],
            demoData: $config['demo_data'] ?? [],
            version: $config['version'] ?? null,
            needSync: $config['need_sync'] ?? false,
            strict: $config['strict'] ?? false
        );
    }

    /**
     * Валидация конфигурации
     */
    private function validate(): void
    {
        if (empty($this->iblock['code'])) {
            throw new \InvalidArgumentException('IBlock code is required');
        }

        if (empty($this->iblock['name'])) {
            throw new \InvalidArgumentException('IBlock name is required');
        }

        if (empty($this->iblock['type'])) {
            throw new \InvalidArgumentException('IBlock type is required');
        }

        // Валидация свойств
        foreach ($this->properties as $code => $property) {
            if (empty($property['name'])) {
                throw new \InvalidArgumentException("Property name is required for '{$code}'");
            }
            if (empty($property['type'])) {
                throw new \InvalidArgumentException("Property type is required for '{$code}'");
            }
        }
    }

    /**
     * Получить код инфоблока
     */
    public function getCode(): string
    {
        return $this->iblock['code'];
    }

    /**
     * Получить название инфоблока
     */
    public function getName(): string
    {
        return $this->iblock['name'];
    }

    /**
     * Получить тип инфоблока
     */
    public function getType(): string
    {
        return $this->iblock['type'];
    }

    /**
     * Получить ID сайта
     */
    public function getSiteId(): string
    {
        return $this->iblock['site_id'] ?? 's1';
    }

    /**
     * Получить сортировку
     */
    public function getSort(): int
    {
        return $this->iblock['sort'] ?? 500;
    }

    /**
     * Получить языковые сообщения
     */
    public function getLanguageMessages(): array
    {
        return $this->iblock['messages'] ?? [
            'ELEMENT_NAME' => 'Элемент',
            'ELEMENTS_NAME' => 'Элементы',
            'ELEMENT_ADD' => 'Добавить элемент',
            'ELEMENT_EDIT' => 'Изменить элемент',
            'ELEMENT_DELETE' => 'Удалить элемент',
            'SECTION_NAME' => 'Раздел',
            'SECTIONS_NAME' => 'Разделы',
        ];
    }

    /**
     * Получить полную конфигурацию инфоблока
     */
    public function getIBlockConfig(): array
    {
        return $this->iblock;
    }

    /**
     * Получить массив свойств
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Получить конфигурацию конкретного свойства
     */
    public function getProperty(string $code): ?array
    {
        return $this->properties[$code] ?? null;
    }

    /**
     * Получить демо-данные
     */
    public function getDemoData(): array
    {
        return $this->demoData;
    }

    /**
     * Есть ли демо-данные
     */
    public function hasDemoData(): bool
    {
        return !empty($this->demoData);
    }

    /**
     * Получить версию конфигурации
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * Нужно ли синхронизировать инфоблок и поля
     */
    public function needSync(): bool
    {
        return $this->needSync;
    }

    /**
     * Жёсткая синхронизация - удалять все элементы не из конфига
     */
    public function isStrict(): bool
    {
        return $this->strict;
    }

    /**
     * Создать IBlockEntity для регистрации
     */
    public function toIBlockEntity(): IBlockEntity
    {
        return new IBlockEntity(
            iblockTypeId: $this->getType(),
            code: $this->getCode(),
            name: $this->getName(),
            languageMessages: $this->getLanguageMessages(),
            siteId: $this->getSiteId(),
            sort: $this->getSort()
        );
    }

    /**
     * Создать PropertyEntity для свойства
     */
    public function toPropertyEntity(string $propertyCode): PropertyEntity
    {
        $property = $this->getProperty($propertyCode);

        if (!$property) {
            throw new \InvalidArgumentException("Property '{$propertyCode}' not found in config");
        }

        // Дополнительные поля
        $additionalFields = [];

        if (isset($property['user_type'])) {
            $additionalFields['USER_TYPE'] = $property['user_type'];
        }

        if (isset($property['settings']) && is_array($property['settings'])) {
            $additionalFields['USER_TYPE_SETTINGS'] = $property['settings'];
        }

        // Для списочных свойств (type=L) добавляем значения
        if ($property['type'] === 'L' && isset($property['values']) && is_array($property['values'])) {
            $additionalFields['VALUES'] = $property['values'];
        }

        return new PropertyEntity(
            code: $propertyCode,
            name: $property['name'],
            propertyType: $property['type'],
            isRequired: $property['required'] ?? false,
            multiple: $property['multiple'] ?? false,
            sort: $property['sort'] ?? 500,
            additionalFields: $additionalFields
        );
    }

    /**
     * Создать массив PropertyEntity для всех свойств
     */
    public function toPropertyEntities(): array
    {
        $entities = [];
        foreach (array_keys($this->properties) as $code) {
            $entities[] = $this->toPropertyEntity($code);
        }
        return $entities;
    }
}
