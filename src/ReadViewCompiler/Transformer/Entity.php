<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

class Entity extends TransformerInitializerAbstract
{
    private $entityClassName;
    private $target;

    public function __construct(string $fieldName, string $entityClassName, $target = null)
    {
        $this->entityClassName = $entityClassName;
        $this->target = $target;
        parent::__construct($fieldName);
    }

    public function getEntityClassName(): string
    {
        return $this->entityClassName;
    }

    public function getTarget()
    {
        return $this->target;
    }
}
