<?php namespace Ewll\CrudBundle\Unit;

interface CreateMethodInterface extends UnitInterface
{
    public function getMutationsOnCreate(): array;
    public function getCreateFormConfig(): array;
//    public function getPreformationClassName(): ?string;
}
