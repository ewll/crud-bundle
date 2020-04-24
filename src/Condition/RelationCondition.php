<?php namespace Ewll\CrudBundle\Condition;

class RelationCondition implements ConditionInterface
{
    const COND_RELATE = 1;
    const COND_NOT_RELATE = 2;

    const ACTION_EQUAL = '=';
    const ACTION_NOT_EQUAL = '<>';

    private $action;
    private $className;
    private $joinCondition;
    private $conditions;

    public function __construct(int $action, string $className, array $joinCondition, array $conditions)
    {
        $this->action = $action;
        $this->className = $className;
        $this->joinCondition = $joinCondition;
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

    public function getJoinCondition(): array
    {
        return $this->joinCondition;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }
}
