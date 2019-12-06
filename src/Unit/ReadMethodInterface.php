<?php namespace Ewll\CrudBundle\Unit;

interface ReadMethodInterface extends UnitInterface
{
    public function getReadOnePreFilters(): array;
    public function getReadOneFields(): array;
    public function getReadListPreFilters(): array;
    public function getReadListFields(): array;
    /** @TODO Переделать на вид форм, а prepareFilters через трансформеры */
    public function getAllowedFilterFields(): array;
    public function getAllowedSortFields(): array;
    public function getPreSort(): array;
}
