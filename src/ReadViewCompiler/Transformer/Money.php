<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

class Money extends TransformerInitializerAbstract
{
    private $isView;

    public function __construct(string $fieldName, bool $isView = false)
    {
        parent::__construct($fieldName);
        $this->isView = $isView;
    }

    public function isView(): bool
    {
        return $this->isView;
    }
}
