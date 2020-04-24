<?php namespace Ewll\CrudBundle\Unit;

interface DeleteMethodInterface extends UnitInterface
{
    public function getDeleteConstraints(): array;
    public function isForceDelete(): bool;
    public function onDelete(object $entity): void;
}
