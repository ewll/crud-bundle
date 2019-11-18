<?php namespace Ewll\CrudBundle\Unit;

use Symfony\Component\Form\FormBuilderInterface;

interface UpdateMethodInterface extends UnitInterface
{
    public function fillUpdateFormBuilder(FormBuilderInterface $formBuilder): void;
    public function getPreformationClassName(): ?string;
}
