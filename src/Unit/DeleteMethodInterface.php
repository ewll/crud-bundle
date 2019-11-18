<?php namespace Ewll\CrudBundle\Unit;

interface DeleteMethodInterface extends UnitInterface
{
    public function getDeleteConstraints(): array;
}
