<?php namespace Ewll\CrudBundle\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class EntityCount extends Constraint
{
    const MESSAGE_KEY_MAX = 1;

    public $messages = [
        self::MESSAGE_KEY_MAX => 'entity.count.max'
    ];
    public $entityClassName;
    public $conditions;
    public $max;
    public $translations;

    public function __construct(string $entityClassName, array $conditions, int $max, array $translations = [])
    {
        $this->entityClassName = $entityClassName;

        parent::__construct();
        $this->conditions = $conditions;
        $this->max = $max;
        $this->translations = $translations;
    }
}
