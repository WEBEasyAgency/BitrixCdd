<?php

namespace BitrixCdd\Services;

use BitrixCdd\Infrastructure\IBlockElementManager;

/**
 * Менеджер для работы с элементами конкретного инфоблока
 */
class IBlockConfigManager
{
    public function __construct(
        private int $iblockId,
        private IBlockElementManager $elementManager
    ) {
    }

    public function getElements(array $params = []): array
    {
        $filter = $params['filter'] ?? [];
        $select = $params['select'] ?? [];
        $order = $params['order'] ?? ['SORT' => 'ASC'];
        $navParams = [];
        if (isset($params['limit'])) {
            $navParams = ['nTopCount' => $params['limit']];
        }

        return $this->elementManager->getElements(
            $this->iblockId,
            $filter,
            $select,
            $order,
            $navParams
        );
    }

    public function getElementById(int $elementId, array $select = []): ?array
    {
        return $this->elementManager->getElementById($elementId, $select);
    }

    public function getIBlockId(): int
    {
        return $this->iblockId;
    }

    public function createElement(array $fields, array $properties = []): int|false
    {
        return $this->elementManager->createElement($this->iblockId, $fields, $properties);
    }

    public function updateElement(int $elementId, array $fields = [], array $properties = []): bool
    {
        return $this->elementManager->updateElement($elementId, $fields, $properties);
    }

    public function deleteElement(int $elementId): bool
    {
        return $this->elementManager->deleteElement($elementId);
    }

    public function getCount(): int
    {
        return $this->elementManager->getElementsCount($this->iblockId);
    }
}
