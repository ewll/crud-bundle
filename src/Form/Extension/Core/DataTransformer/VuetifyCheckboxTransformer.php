<?php namespace Ewll\CrudBundle\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class VuetifyCheckboxTransformer implements DataTransformerInterface
{
    private $trueValue;

    public function __construct(string $trueValue)
    {
        $this->trueValue = $trueValue;
    }

    public function transform($value)
    {
        return (bool)$value;
    }

    public function reverseTransform($value)
    {
        return $value === 'true' || $value === 1 || $value === '1';
    }
}
