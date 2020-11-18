<?php //namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;
//
//use Ewll\CrudBundle\ReadViewCompiler\Context;
//use Ewll\DBBundle\Query\QueryBuilder;
//use Ewll\DBBundle\Repository\FilterExpression;
//use Ewll\DBBundle\Repository\RepositoryProvider;
//use LogicException;
//use RuntimeException;
//
//class EntityRelationTransformer implements ViewTransformerInterface
//{
//    private $repositoryProvider;
//
//    public function __construct(RepositoryProvider $repositoryProvider)
//    {
//        $this->repositoryProvider = $repositoryProvider;
//    }
//
//    public function transform(
//        ViewTransformerInitializerInterface $initializer,
//        $item,
//        array $transformMap,
//        Context $context = null
//    ) {
//        if (!$initializer instanceof EntityRelation) {
//            throw new RuntimeException(
//                "Expected '" . EntityRelation::class . "', got '" . get_class($initializer) . "'"
//            );
//        }
//
//        $fieldName = $initializer->getFieldName();
//
//        $entityClassName = $initializer->getEntityClassName();
//        $repository = $this->repositoryProvider->get($entityClassName);
//        $qb = new QueryBuilder($repository);
//
//        if ($initializer->hasBeforeRequest()) {
//            call_user_func($initializer->getBeforeRequest(), $qb);
//        }
//        if (null !== $context) {
//            $contextParameterKey = $this->compileContextParameterKey($transformMap);
//            if (!$context->has($contextParameterKey)) {
//                $itemIdsForFind = $this->getItemIdsForFind($context, $transformMap);
//                $qb->addCondition(new FilterExpression(FilterExpression::ACTION_IN, $fieldName, $itemIdsForFind));
//                $allEntities = $repository->find($qb);
//                $allEntitiesIndexedByItemId = [];
//                foreach ($allEntities as $entity) {
//                    if (!isset($allEntitiesIndexedByItemId[$entity->$fieldName])) {
//                        $allEntitiesIndexedByItemId[$entity->$fieldName] = [];
//                    }
//                    $allEntitiesIndexedByItemId[$entity->$fieldName][] = $entity;
//                }
//                $context->set($contextParameterKey, $allEntitiesIndexedByItemId);
//            } else {
//                $allEntitiesIndexedByItemId = $context->get($contextParameterKey);
//            }
//            $entities = $allEntitiesIndexedByItemId[$item->id] ?? [];
//        } else {
//            $qb->addCondition(new FilterExpression(FilterExpression::ACTION_EQUAL, $fieldName, $item->id));
//            $entities = $repository->find($qb);
//        }
//
//        $target = $initializer->getTarget();
//        if (null !== $target) {
//            $result = [];
//            foreach ($entities as $entity) {
//                if (is_array($target)) {
//                    $result[] = call_user_func_array([$entity, $target[0]], $target[1]);
//                }
//                elseif (is_callable([$entity, $target])) {
//                    $result[] = call_user_func([$entity, $target]);
//                } else {
//                    $result[] = $entity->$target;
//                }
//            }
//        } else {
//            $result = $entities;
//        }
//
//        return $result;
//    }
//
//    private function compileContextParameterKey(array $transformMap, int $depth = null): string
//    {
//        $key = 'EntityRelationTransformer_';
//        if (null === $depth) {
//            $elements = $transformMap;
//        } else {
//            $elements = [];
//            for ($i=0;$i<$depth;$i++) {
//                $elements[] = $transformMap[$i];
//            }
//        }
//
//        $key .=  implode('_', $elements);
//
//        return $key;
//    }
//
//    private function getItemIdsForFind(Context $context, array $transformMap): array
//    {
//        $transformMapNum = count($transformMap);
//        if ($transformMapNum === 1) {
//            $items = $context->getItems();
//        } elseif ($transformMapNum > 1) {
//            $contextParameterKey = $this->compileContextParameterKey($transformMap, $transformMapNum - 1);
//            $items = $context->get($contextParameterKey);
//        } else {
//            throw new LogicException("Number of transformMap items: $transformMapNum, expect greater then 0");
//        }
//
//        if (null === $items) {
//            throw new LogicException('Items not found in Context for ' . $this->compileContextParameterKey($transformMap));
//        }
//
//        $ids = array_column($items, 'id');
//
//        return $ids;
//    }
//}
