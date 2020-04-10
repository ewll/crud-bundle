<?php namespace Ewll\CrudBundle\Unit;

use Ewll\CrudBundle\Form\FormConfig;

interface ReadMethodInterface extends UnitInterface
{
    public function getReadOnePreConditions(): array;
    public function getReadOneFields(): array;
    public function getReadListPreConditions(): array;
    public function getReadListFields(): array;
    public function getFiltersFormConfig(): ?FormConfig;
    public function getAllowedSortFields(): array;
    public function getPreSort(): array;
    public function getReadListExtraData(array $context): array;
}
