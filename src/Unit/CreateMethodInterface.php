<?php namespace Ewll\CrudBundle\Unit;

interface CreateMethodInterface extends UnitInterface
{
    public function getCreateFormConfig(): array;
    public function getMutationsOnCreate($entity): array;
//    public function getPreformationClassName(): ?string;
}
