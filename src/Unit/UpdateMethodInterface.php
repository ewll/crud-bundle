<?php namespace Ewll\CrudBundle\Unit;


interface UpdateMethodInterface extends UnitInterface
{
    public function getUpdateFormConfig(): array;
//    public function getPreformationClassName(): ?string;
    public function getMutationsOnUpdate(): array;
}
