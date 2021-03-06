<?php namespace Ewll\CrudBundle\Condition;

class ExpressionCondition implements ConditionInterface
{
    const ACTION_EQUAL = 1;
    const ACTION_NOT_EQUAL = 2;
    const ACTION_GREATER = 3;

    private $action;
    private $field;
    private $value;

    public function __construct(int $action, $field, $value)
    {
        $this->action = $action;
        $this->field = $field;
        $this->value = $value;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getField()
    {
        return $this->field;
    }

    public function getValue()
    {
        return $this->value;
    }
}
