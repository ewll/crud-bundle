<?php namespace Ewll\CrudBundle\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class EntityAccess extends Constraint
{
    const MESSAGE_KEY_NOT_EXISTS = 1;

    public $messages = [
        self::MESSAGE_KEY_NOT_EXISTS => 'entity.not-exists'
    ];
    public $entityClassName;

    public function __construct(string $entityClassName)
    {
        $this->entityClassName = $entityClassName;

        parent::__construct();
    }
}
