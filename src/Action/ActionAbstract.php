<?php namespace Ewll\CrudBundle\Action;

abstract class ActionAbstract implements ActionInterface
{
    private $methodName;
    private $unitName;
    private $id;
    private $data;

    public function __construct(string $methodName, string $unitName, int $id = null, array $data = null)
    {
        $this->methodName = $methodName;
        $this->unitName = $unitName;
        $this->id = $id;
        $this->data = $data;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getUnitName(): string
    {
        return $this->unitName;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getData(): ?array
    {
        return $this->data;
    }
}
