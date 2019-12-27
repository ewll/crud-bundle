<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

use Ewll\CrudBundle\ReadViewCompiler\Context;
use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;

class EntityTransformer implements ViewTransformerInterface
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function transform(ViewTransformerInitializerInterface $initializer, $item, Context $context = null)
    {
        if (!$initializer instanceof Entity) {
            throw new RuntimeException("Expected '".Entity::class."', got '".get_class($initializer)."'");
        }

        $fieldName = $initializer->getFieldName();
        $field = $item->$fieldName;

        if (null === $field) {
            return null;
        }
        $entityClassName = $initializer->getEntityClassName();
        $repository = $this->repositoryProvider->get($entityClassName);
//@TODO \Ewll\CrudBundle\Unit\Item\ApplicationItemCrudUnit
        if (null !== $context) {
            $contextParameterKey = "entities_of_{$fieldName}_indexed_by_id";
            if (!$context->has($contextParameterKey)) {
                $entitiesIndexedById = $repository->findByRelativeIndexed($context->getItems(), $fieldName);
                $context->set($contextParameterKey, $entitiesIndexedById);
            } else {
                $entitiesIndexedById = $context->get($contextParameterKey);
            }
            $entity = $entitiesIndexedById[$field] ?? null;
        } else {
//            throw new RuntimeException('hz');
            $entity = $repository->findById($field);
        }

        if (null === $entity) {
            throw new RuntimeException("Entity '$entityClassName' with id '$field' not found");
        }

        $target = $initializer->getTarget();
        if (null !== $target) {
            if (is_callable([$entity, $target])) {
                $result = call_user_func([$entity, $target]);
            } else {
                $result = $entity->$target;
            }
        } else {
            $result = $entity;
        }

        return $result;
    }
}
