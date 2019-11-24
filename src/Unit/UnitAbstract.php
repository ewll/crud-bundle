<?php namespace Ewll\CrudBundle\Unit;

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

    public function getAllowedFilterFields(): array
    {
        return [];
    }

    public function prepareFilters(array $filters): array
    {
        return $filters;
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

    public function getMutationsOnUpdate(): array
    {
        return [];
    }

    public function getMutationsOnCreate(): array
    {
        return [];
    }
}
