<?php namespace Ewll\CrudBundle\Unit;

use Ewll\CrudBundle\Form\FormConfig;

interface ReadMethodInterface extends UnitInterface
{
    public function getReadOnePreFilters(): array;
    public function getReadOneFields(): array;
    public function getReadListPreFilters(): array;
    public function getReadListFields(): array;
    public function getFiltersFormConfig(): ?FormConfig;
    public function getAllowedSortFields(): array;
    public function getPreSort(): array;
}
