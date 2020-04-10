<?php namespace Ewll\CrudBundle\Unit;

use Ewll\CrudBundle\Condition\ConditionInterface;

interface UnitInterface
{
    public function getUnitName(): string;
    public function getEntityClass(): string ;
    public function getSourceClassName(): string;
    public function getAccessRuleClassName(): ?string;
    /** @return ConditionInterface[] */
    public function getAccessConditions(string $action): array;
    public function getCustomActions(): array;
}
