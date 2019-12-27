<?php namespace Ewll\CrudBundle\Unit;

use Ewll\CrudBundle\AccessCondition\AccessConditionInterface;

interface UnitInterface
{
    public function getUnitName(): string;
    public function getEntityClass(): string ;
    public function getAccessRuleClassName(): ?string;
    /** @return AccessConditionInterface[] */
    public function getAccessConditions(string $action): array;
    public function getCustomActions(): array;
}
