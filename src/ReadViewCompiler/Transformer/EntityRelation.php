<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

class EntityRelation extends TransformerInitializerAbstract
{
    private $entityClassName;
    private $target;
    private $beforeRequest;

    public function __construct(
        string $fieldName,
        string $entityClassName,
        $target = null,
        callable $beforeRequest = null
    ) {
        $this->entityClassName = $entityClassName;
        $this->beforeRequest = $beforeRequest;
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

    public function getBeforeRequest(): ?callable
    {
        return $this->beforeRequest;
    }

    public function hasBeforeRequest(): bool
    {
        return null !== $this->beforeRequest;
    }
}
