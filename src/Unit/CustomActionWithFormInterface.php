<?php namespace Ewll\CrudBundle\Unit;

use Ewll\CrudBundle\Form\FormConfig;

interface CustomActionWithFormInterface
{
    public function getFormConfig(object $entity): FormConfig;
}
