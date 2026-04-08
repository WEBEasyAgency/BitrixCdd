<?php

namespace BitrixCdd\Infrastructure;

/**
 * Фабрика для создания IBlockBuilder с предварительно инжектированными менеджерами
 *
 * Решает проблему отложенного создания builder-а:
 * значение needSync известно только после загрузки global.php,
 * но менеджеры (IBlockManager, PropertyManager) можно инжектировать заранее.
 */
class IBlockBuilderFactory
{
    public function __construct(
        private readonly IBlockManager $iblockManager,
        private readonly PropertyManager $propertyManager
    ) {
    }

    /**
     * Создать IBlockBuilder с заданным значением needSync
     *
     * @param bool $needSync Нужно ли синхронизировать структуру
     * @return IBlockBuilder
     */
    public function create(bool $needSync = true): IBlockBuilder
    {
        return new IBlockBuilder($needSync, $this->iblockManager, $this->propertyManager);
    }

    public function getIBlockManager(): IBlockManager
    {
        return $this->iblockManager;
    }
}
