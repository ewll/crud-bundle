<?php namespace Ewll\CrudBundle\Source;

use Ewll\CrudBundle\Exception\EntityNotFoundException;

interface SourceInterface
{
    /** @throws EntityNotFoundException */
    public function getById(string $entityClassName, int $id, array $accessConditions): object;

    public function create(object $item, callable $onCreate): void;

    public function update(object $item, array $options, callable $onUpdate = null): void;

    public function findOne(string $entityClassName, array $conditions): ?object;

    public function findList(
        string $entityClassName,
        array $conditions,
        int $page,
        int $itemsPerPage,
        array $sort
    ): ItemsList;
}
