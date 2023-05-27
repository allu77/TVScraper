<?php

declare(strict_types=1);

namespace DataProviders\DB;

use SebastianBergmann\Type\GenericObjectType;

class GenericItem
{
    private array $properties;
    private ?string $id = null;
    private GenericItem $parentItem;

    public function __construct(array $properties)
    {
        $this->properties = $properties;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getId(): ?string
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

    public function getProperty(string $property): mixed
    {
        return $this->properties[$property];
    }
    public function setProperty(string $property, mixed $value): void
    {
        $this->properties[$property] = $value;
    }

    /**
     * Converts all airDate and pubDate from relative to actual - WARNING can be called only once
     * @param ?int $now - time to actualized dates to, defaults to time() if not specified
     * @return GenericItem - self
     */

    public function actualizeDates(?int $now = null): GenericItem
    {
        if ($now === null) $now = time();

        if (array_key_exists('airDate', $this->properties)) {
            $this->properties['airDate'] = $now + intval($this->properties['airDate']);
        }
        if (array_key_exists('pubDate', $this->properties)) {
            $this->properties['pubDate'] = $now + intval($this->properties['pubDate']);
        }

        return $this;
    }
}
