<?php namespace Ewll\CrudBundle\Unit;

use Symfony\Component\Form\FormBuilderInterface;

interface CreateMethodInterface extends UnitInterface
{
    public function fillCreateFormBuilder(FormBuilderInterface $formBuilder): void;
    public function getPreformationClassName(): ?string;
}
