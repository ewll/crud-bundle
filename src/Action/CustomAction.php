<?php namespace Ewll\CrudBundle\Action;

class CustomAction extends ActionAbstract
{
    private $customActionName;

    public function __construct(string $method, string $unitName, array $data, string $customActionName, int $id = null)
    {
        $this->customActionName = $customActionName;

        parent::__construct($method, $unitName, $id, $data);
    }

    public function getCustomActionName(): string
    {
        return $this->customActionName;
    }
}
