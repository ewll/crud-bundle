<?php namespace Ewll\CrudBundle\Source;

use Ewll\CrudBundle\Condition;
use Ewll\CrudBundle\Exception\EntityNotFoundException;
use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Query\QueryBuilder;
use Ewll\DBBundle\Repository\FilterExpression;
use Ewll\DBBundle\Repository\RepositoryProvider;

class DbSource implements SourceInterface
{
    private $repositoryProvider;
    private $defaultDbClient;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        DbClient $defaultDbClient
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->defaultDbClient = $defaultDbClient;
    }

    /** @inheritDoc */
    public function getById(string $entityClassName, int $id, array $accessConditions): object
    {
        $repository = $this->repositoryProvider->get($entityClassName);
        $qb = new QueryBuilder($repository);
        $filters = ['id' => $id, 'isDeleted' => 0];
        $filters = array_merge($filters, $this->convertConditionToFilterExpression($qb, $accessConditions));
        $qb
            ->addConditions($filters)
            ->setLimit(1);
        $entity = $repository->find($qb);
        if (null === $entity) {
            throw new EntityNotFoundException();
        }

        return $entity;
    }

    public function create(object $item, callable $onCreate): void
    {
        $repository = $this->repositoryProvider->get(get_class($item));
        $this->defaultDbClient->beginTransaction();
        try {
            $repository->create($item);
            $onCreate();
            $this->defaultDbClient->commit();
        } catch (\Exception $e) {
            $this->defaultDbClient->rollback();

            throw new \RuntimeException("Transaction: {$e->getMessage()}", 0, $e);
        }
    }

    public function update(object $item, array $options, callable $onUpdate = null): void
    {
        $repository = $this->repositoryProvider->get(get_class($item));
        if (!array_key_exists('fields', $options)) {
            throw new \RuntimeException("Option 'fields' is required");
        }
        $this->defaultDbClient->beginTransaction();
        try {
            if (count($options['fields']) > 0) {
                $repository->update($item, $options['fields']);
            }
            if (null !== $onUpdate) {
                $onUpdate();
            }
            $this->defaultDbClient->commit();
        } catch (\Exception $e) {
            $this->defaultDbClient->rollback();

            throw new \RuntimeException("Transaction: {$e->getMessage()}", 0, $e);
        }
    }

    public function findOne(string $entityClassName, array $conditions): ?object
    {
        $repository = $this->repositoryProvider->get($entityClassName);
        $qb = new QueryBuilder($repository);
        $filters = $this->convertConditionToFilterExpression($qb, $conditions);
        $qb
            ->addConditions($filters)
            ->setLimit(1);
        $item = $repository->find($qb);

        return $item;
    }

    public function findList(
        string $entityClassName,
        array $conditions,
        int $page,
        int $itemsPerPage,
        array $sort
    ): ItemsList {
        $repository = $this->repositoryProvider->get($entityClassName);
        $qb = new QueryBuilder($repository);
        $filters = $this->convertConditionToFilterExpression($qb, $conditions);
        $qb
            ->addConditions($filters)
            ->setSort($sort)
            ->setPage($page, $itemsPerPage);
        $items = $repository->find($qb);
        $total = $repository->getFoundRows();
        $itemsList = new ItemsList($items, $total);

        return $itemsList;
    }

    /**
     * @param Condition\ConditionInterface[] $accessConditions
     * @return FilterExpression[]
     */
    private function convertConditionToFilterExpression(QueryBuilder $qb, array $accessConditions)
    {
        $filters = [];
        $acCount = 0;
        foreach ($accessConditions as $accessCondition) {
            $acCount++;
            if ($accessCondition instanceof Condition\ExpressionCondition) {
                $field = $accessCondition->getField();
                $value = $accessCondition->getValue();
                $isValueArray = is_array($value);
                switch ($accessCondition->getAction()) {
                    case Condition\ExpressionCondition::ACTION_EQUAL:
                        $filterAction = $isValueArray
                            ? FilterExpression::ACTION_IN
                            : FilterExpression::ACTION_EQUAL;
                        $filters[] = new FilterExpression($filterAction, $field, $value);
                        break;
                    case Condition\ExpressionCondition::ACTION_NOT_EQUAL:
                        $filterAction = $isValueArray
                            ? FilterExpression::ACTION_NOT_IN
                            : FilterExpression::ACTION_NOT_EQUAL;
                        $filters[] = new FilterExpression($filterAction, $field, $value);
                        break;
                    case Condition\ExpressionCondition::ACTION_GREATER:
                        $filters[] = new FilterExpression(FilterExpression::ACTION_GREATER, $field, $value);
                        break;
                    default:
                        throw new \RuntimeException('Unknown ExpressionCondition action');
                }
            } elseif ($accessCondition instanceof Condition\RelationCondition) {
                $joinEntityRepository = $this->repositoryProvider->get($accessCondition->getClassName());
                $tableName = $joinEntityRepository->getEntityConfig()->tableName;
                $prefix = "ac{$acCount}";
                $mainPrefix = $qb->getPrefix();
                $conditions = [];
                foreach ($accessCondition->getConditions() as $condition) {
                    switch ($condition['type']) {
                        case 'field':
                            $value = "{$mainPrefix}.{$condition['value']}";
                            break;
                        case 'value':
                            $value = $condition['value'];//@TODO BINDING VALUE
                            break;
                        default:
                            throw new \RuntimeException('Unknown RelationCondition field type');
                    }
                    $conditions[] = "{$prefix}.{$condition['field']} {$condition['action']} {$value}";
                }
                $qb
                    ->addJoin($tableName, $prefix, implode(' AND ', $conditions), 'LEFT');
                switch ($accessCondition->getAction()) {
                    case Condition\RelationCondition::COND_RELATE:
                        $filterAction = FilterExpression::ACTION_IS_NOT_NULL;
                        break;
                    case Condition\RelationCondition::COND_NOT_RELATE:
                        $filterAction = FilterExpression::ACTION_IS_NULL;
                        break;
                    default:
                        throw new \RuntimeException('Unknown RelationCondition action');
                }
                $qb->addCondition(new FilterExpression($filterAction, [$prefix, 'id']));
            } else {
                throw new \RuntimeException('Unknown Condition \'' . get_class($accessCondition) . '\'');
            }
        }

        return $filters;
    }
}
