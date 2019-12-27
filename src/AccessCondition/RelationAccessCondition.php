<?php namespace Ewll\CrudBundle\AccessCondition;

class RelationAccessCondition implements AccessConditionInterface
{
    const COND_RELATE = 1;
    const COND_NOT_RELATE = 2;

    private $action;
    private $className;
    private $conditions;

    public function __construct(int $action, string $className, array $conditions)
    {
        $this->action = $action;
        $this->className = $className;
        $this->conditions = $conditions;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }
}
