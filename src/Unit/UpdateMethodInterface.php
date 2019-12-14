<?php namespace Ewll\CrudBundle\Unit;

use Ewll\CrudBundle\Form\FormConfig;

interface UpdateMethodInterface extends UnitInterface
{
    public function getUpdateFormConfig(object $entity): FormConfig;
    public function getMutationsOnUpdate(object $entity): array;
//    public function getPreformationClassName(): ?string;
}
