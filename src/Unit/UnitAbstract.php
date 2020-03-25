<?php namespace Ewll\CrudBundle\Unit;

use Ewll\CrudBundle\Form\FormConfig;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Authenticator\Authenticator;

abstract class UnitAbstract implements UnitInterface
{
    protected $repositoryProvider;
    protected $authenticator;

    public function __construct(RepositoryProvider $repositoryProvider, Authenticator $authenticator)
    {
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
    }

    public function getReadOnePreFilters(): array
    {
        return [];
    }

    public function getReadListPreFilters(): array
    {
        return [];
    }

    public function getFiltersFormConfig(): ?FormConfig
    {
        return null;
    }


    public function getAllowedFilterFields(): array
    {
        return [];
    }

    public function getCreateFormConfig(): ?FormConfig
    {
        return null;
    }

    public function getAllowedSortFields(): array
    {
        return ['id'];
    }

    public function getPreSort(): array
    {
        return [];
    }

    public function getDeleteConstraints(): array
    {
        return [];
    }

    public function getAccessRuleClassName(): ?string
    {
        return null;
    }

//    public function getPreformationClassName(): ?string
//    {
//        return null;
//    }

//    public function fillUpdateFormBuilder(FormBuilderInterface $formBuilder): void
//    {
//        $unit = $this;
//        if (!$unit instanceof CreateMethodInterface) {
//            throw new LogicException('Unit' . static::class . ' must implement ' . CreateMethodInterface::class);
//        }
//
//        $unit->fillCreateFormBuilder($formBuilder);
//    }

    public function getMutationsOnUpdate(object $entity): array
    {
        return [];
    }

    public function onCreate(object $entity): void
    {
    }

    public function getMutationsOnCreate(object $entity): array
    {
        return [];
    }

    public function getCustomActions(): array
    {
        return [];
    }

    public function onUpdate(object $entity): void
    {
    }

    public function getReadListExtraData(): array
    {
        return [];
    }
}
