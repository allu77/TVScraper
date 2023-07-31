<?php

namespace modules\DB\Engines;

interface DBInterface
{
    public function beginTransaction(): bool;
    public function inTransaction(): bool;
    public function rollBack(): bool;
    public function commit(): bool;

    public function save(): bool;

    public function addElement(string $elementStore, ?string $parentKey = null, ?string $keyValue = null, array $params): array|bool;
    public function setElement(string $elementStore, string $elementKey, string $keyValue, array $params): array|bool;
    public function removeElement(string $elementStore, string $elementKey, string $keyValue): array|bool;
    public function getElementByKey(string $elementStore, ?string $keyValue = null): array|bool;
    public function getElementByParentKey(string $elementStore, string $parentKey): array|bool;
    public function getElementByAttribute(string $elementStore, string $attribute, string $value): array|bool;
}
