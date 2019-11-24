<?php namespace Ewll\CrudBundle\Unit;

interface UnitInterface
{
    public function getUnitName(): string;
    public function getEntityClass(): string ;
    public function getAccessRuleClassName(): ?string;
    public function getAccessConditions(): array;
}
