<?php namespace Ewll\CrudBundle\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class EntityAccessByUnit extends Constraint
{
    public $message = 'Access denied';
    public $entityClassName;
    public $unitClassName;

    public function __construct(string $entityClassName, string $unitClassName)
    {
        $this->entityClassName = $entityClassName;
        $this->unitClassName = $unitClassName;

        parent::__construct();
    }
}
