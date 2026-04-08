<?php

namespace BitrixCdd\Infrastructure;

/**
 * Построитель для регистрации инфоблоков со всеми зависимостями
 * Регистрирует: инфоблок -> свойства
 * Типы инфоблоков должны быть уже зарегистрированы перед использованием builder
 */
class IBlockBuilder
{
    private IBlockManager $iblockManager;
    private PropertyManager $propertyManager;
    private bool $needSync;

    public function __construct(
        bool $needSync,
        IBlockManager $iblockManager,
        PropertyManager $propertyManager
    ) {
        $this->needSync = $needSync;
        $this->iblockManager = $iblockManager;
        $this->propertyManager = $propertyManager;
    }

    /**
     * Построить и зарегистрировать инфоблок с свойствами
     *
     * @param array $config Конфигурация инфоблока
     * @return int ID созданного инфоблока
     */
    public function build(array $config): int
    {
        // 1. Регистрируем инфоблок (тип должен уже существовать)
        $iblockId = $this->iblockManager->ensureIblockExists(
            $config['type']['id'],
            $config['iblock']['code'],
            $config['iblock']['data'],
            $this->needSync,
            $config['iblock_fields'] ?? []
        );

        // 2. Добавляем свойства если указаны
        if (!empty($config['properties'])) {
            foreach ($config['properties'] as $propCode => $propData) {
                $this->propertyManager->addProperty($iblockId, $propCode, $propData, $this->needSync);
            }
        }

        return $iblockId;
    }
}
