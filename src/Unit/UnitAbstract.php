<?php namespace Ewll\CrudBundle\Unit;

use Ewll\CrudBundle\Form\FormConfig;
use Ewll\CrudBundle\Source\DbSource;
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

    public function getSourceClassName(): string
    {
        return DbSource::class;
    }

    public function getReadOneFields(): array
    {
        return [];
    }

    public function getReadListFields(): array
    {
        return [];
    }

    public function getReadOnePreConditions(): array
    {
        return [];
    }

    public function getReadListPreConditions(): array
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

    public function getMutationsOnUpdate(object $entity): array
    {
        return [];
    }

    public function beforeCreate(object $entity, array $formData): void
    {
    }

    public function onCreate(object $entity, array $formData): void
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

    public function getReadListExtraData(array $context): array
    {
        return [];
    }

    public function getCreateExtraData(object $entity): array
    {
        return [];
    }
}
