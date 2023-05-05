<?php

declare(strict_types=1);

namespace DataProviders\DB;

class GenericItem
{
    private array $properties;
    private string $id;
    private GenericItem $parentItem;

    public function __construct(array $properties)
    {
        $this->properties = $properties;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setParentItem(GenericItem $item): void
    {
        $this->parentItem = $item;
    }

    public function getParentId(): string
    {
        return $this->getParentItem()->getId();
    }

    public function getParentItem(): GenericItem
    {
        return $this->parentItem;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getProperty(string $property): string
    {
        return $this->properties[$property];
    }
}
