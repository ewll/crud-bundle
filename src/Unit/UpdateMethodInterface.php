<?php namespace Ewll\CrudBundle\Unit;


interface UpdateMethodInterface extends UnitInterface
{
    public function getUpdateFormConfig(): array;
    public function getMutationsOnUpdate($entity): array;
//    public function getPreformationClassName(): ?string;
}
