<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

class TransformersGroup
{
    private $item;
    private $fields;

    public function __construct($item, array $fields)
    {
        $this->item = $item;
        $this->fields = $fields;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getItem()
    {
        return $this->item;
    }
}
