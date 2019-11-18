<?php namespace Ewll\CrudBundle\Constraint;

use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class EntityAccessByUnitValidator extends ConstraintValidator
{
    private $repositoryProvider;
    private $crudUnits;

    public function __construct(RepositoryProvider $repositoryProvider, iterable $crudUnits)
    {
        $this->repositoryProvider = $repositoryProvider;
        $this->crudUnits = $crudUnits;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof EntityAccessByUnit) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\EntityAccessByUnit');
        }

        if (null === $value || '' === $value) {
            return;
        }

        $entityId = (int) $value;
        $entity = $this->repositoryProvider->get($constraint->entityClassName)->findById($entityId);

        $unit = null;
        foreach ($this->crudUnits as $crudUnit) {
            if ($crudUnit instanceof $constraint->unitClassName) {
                $unit = $crudUnit;
            }
        }
        if (null === $unit) {
            throw new RuntimeException('Unit not found');
        }
        $accessConditions = $unit->getAccessConditions();

        foreach ($accessConditions as $field => $value) {
            if ($entity->$field !== $value) {
                $this->context->buildViolation($constraint->message)->addViolation();
            }
        }
    }
}
