<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

use Ewll\CrudBundle\ReadViewCompiler\Context;
use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;

class OneToManyTransformer implements ViewTransformerInterface
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }
//@TODO перенести в DB-bundle
    public function transform(ViewTransformerInitializerInterface $initializer, $item, Context $context = null)
    {
        if (!$initializer instanceof OneToMany) {
            throw new RuntimeException("Expected '".OneToMany::class."', got '".get_class($initializer)."'");
        }

        $fieldName = $initializer->getFieldName();

        $entityClassName = $initializer->getEntityClassName();
        $repository = $this->repositoryProvider->get($entityClassName);

//        if (null !== $context) {
//            $contextParameterKey = "{$entityClassName}_distributed_by_parentId";
//            if (!$context->has($contextParameterKey)) {
//
//                $entitiesIndexedById = $repository->findBy([$fieldName => ]);
//                $context->set($contextParameterKey, $entitiesIndexedById);
//            } else {
//                $entitiesIndexedById = $context->get($contextParameterKey);
//            }
//            $entity = $entitiesIndexedById[$field] ?? null;
//        } else {
        $entities = $repository->findBy([$fieldName => $item->id]);
//        }

        return $entities;
    }
}
