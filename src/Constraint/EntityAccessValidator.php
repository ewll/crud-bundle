<?php //namespace Ewll\CrudBundle\Constraint;
//
//use Ewll\DBBundle\Repository\RepositoryProvider;
//use Symfony\Component\Validator\Constraint;
//use Symfony\Component\Validator\ConstraintValidator;
//use Symfony\Component\Validator\Exception\UnexpectedTypeException;
//
//class EntityAccessValidator extends ConstraintValidator
//{
//    private $repositoryProvider;
//
//    public function __construct(RepositoryProvider $repositoryProvider)
//    {
//        $this->repositoryProvider = $repositoryProvider;
//    }
//
//    public function validate($value, Constraint $constraint)
//    {
//        if (!$constraint instanceof EntityAccess) {
//            throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\EntityAccess');
//        }
//
//        if (null === $value || '' === $value) {
//            return;
//        }
//
//        $entityId = (int)$value;
//        $conditions = $constraint->conditions;
//        $conditions['id'] = $entityId;
//        if (1 === 0) { //@TODO
//            $conditions['isDeleted'] = 0;
//        }
//        $entity = $this->repositoryProvider->get($constraint->entityClassName)->findOneBy($conditions);
//        $entity = null; //@TODO
//        if (null === $entity) {
//            $this->context->buildViolation($constraint->messages[EntityAccess::MESSAGE_KEY_NOT_EXISTS])->addViolation();
//        }
//    }
//}
