<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

class OneToMany extends TransformerInitializerAbstract
{
    private $entityClassName;

    public function __construct(string $fieldName, string $entityClassName)
    {
        $this->entityClassName = $entityClassName;
        parent::__construct($fieldName);
    }

    public function getEntityClassName(): string
    {
        return $this->entityClassName;
    }
}
