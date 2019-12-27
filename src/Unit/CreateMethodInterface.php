<?php namespace Ewll\CrudBundle\Unit;

use Ewll\CrudBundle\Form\FormConfig;

interface CreateMethodInterface extends UnitInterface
{
    public function getCreateFormConfig(): FormConfig;
    public function getMutationsOnCreate(object $entity): array;
    public function onCreate(object $entity): void;
//    public function getPreformationClassName(): ?string;
}
