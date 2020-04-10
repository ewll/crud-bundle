<?php namespace Ewll\CrudBundle\Condition;

class RelationCondition implements ConditionInterface
{
    const COND_RELATE = 1;
    const COND_NOT_RELATE = 2;

    const ACTION_EQUAL = '=';
    const ACTION_NOT_EQUAL = '<>';

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
