<?php namespace Ewll\CrudBundle\Unit;

interface ReadMethodInterface extends UnitInterface
{
    public function getReadOneFields(): array;
    public function getReadManyFields(): array;
    /** @TODO Переделать на вид форм, а prepareFilters через трансформеры */
    public function getAllowedFilterFields(): array;
    public function prepareFilters(array $filters): array;
    public function getAllowedSortFields(): array;
    public function getPreSort(): array;
}
