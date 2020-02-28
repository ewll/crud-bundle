<?php namespace Ewll\CrudBundle\Constraint;

use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class EntityCountValidator extends ConstraintValidator
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof EntityCount) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\EntityCount');
        }

        if (null === $value || '' === $value) {
            return;
        }

        $conditions = $constraint->conditions;
        $cartItems = $this->repositoryProvider->get($constraint->entityClassName)->findBy($conditions);
        if (count($cartItems) >= $constraint->max) {
            $violation = isset($constraint->translations[EntityCount::MESSAGE_KEY_MAX])
                ? $constraint->translations[EntityCount::MESSAGE_KEY_MAX]
                : $constraint->messages[EntityCount::MESSAGE_KEY_MAX];
            $parameters = ['{{ limit }}' => $constraint->max];
            $this->context->buildViolation($violation, $parameters)->addViolation();
        }
    }
}
