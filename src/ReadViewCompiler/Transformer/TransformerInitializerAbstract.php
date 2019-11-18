<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

abstract class TransformerInitializerAbstract implements ViewTransformerInitializerInterface
{
    protected $fieldName;

    public function __construct(string $fieldName)
    {
        $this->fieldName = $fieldName;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }
}
