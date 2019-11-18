<?php namespace Ewll\CrudBundle\Preformation;

use Symfony\Component\Form\FormBuilderInterface;

interface PreformationInterface
{
    public function fillPreformBuilder(FormBuilderInterface $formBuilder, array $data): void;
    public function transform(array $data): array;
    public function reverse($entity): array;
}
