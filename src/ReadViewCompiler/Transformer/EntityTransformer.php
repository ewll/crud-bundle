<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

use Ewll\CrudBundle\ReadViewCompiler\Context;
use Ewll\DBBundle\Repository\RepositoryProvider;
use LogicException;
use RuntimeException;

class EntityTransformer implements ViewTransformerInterface
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function transform(
        ViewTransformerInitializerInterface $initializer,
        $item,
        array $transformMap,
        Context $context = null
    ) {
        if (!$initializer instanceof Entity) {
            throw new RuntimeException("Expected '" . Entity::class . "', got '" . get_class($initializer) . "'");
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
            $contextParameterKey = $this->compileContextParameterKey($transformMap);
            if (!$context->has($contextParameterKey)) {
                $itemsForFind = $this->getItemsForFind($context, $transformMap);
                $entitiesIndexedById = $repository->findByRelativeIndexed($itemsForFind, $fieldName);
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
            if (is_array($target)) {
                $result = call_user_func_array([$entity, $target[0]], $target[1]);
            }
            elseif (is_callable([$entity, $target])) {
                $result = call_user_func([$entity, $target]);
            } else {
                $result = $entity->$target;
            }
        } else {
            $result = $entity;
        }

        return $result;
    }

    private function compileContextParameterKey(array $transformMap, int $depth = null): string
    {
        $key = 'EntityTransformer_';
        if (null === $depth) {
            $elements = $transformMap;
        } else {
            $elements = [];
            for ($i=0;$i<$depth;$i++) {
                $elements[] = $transformMap[$i];
            }
        }

        $key .=  implode('_', $elements);

        return $key;
    }

    private function getItemsForFind(Context $context, array $transformMap): array
    {
        $transformMapNum = count($transformMap);
        if ($transformMapNum === 1) {
            $items = $context->getItems();
        } elseif ($transformMapNum > 1) {
            $contextParameterKey = $this->compileContextParameterKey($transformMap, $transformMapNum - 1);
            $items = $context->get($contextParameterKey);
        } else {
            throw new LogicException("Number of transformMap items: $transformMapNum, expect greater then 0");
        }

        if (null === $items) {
            throw new LogicException('Items not found in Context for ' . $this->compileContextParameterKey($transformMap));
        }

        return $items;
    }
}
