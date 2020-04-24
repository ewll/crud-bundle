<?php namespace Ewll\CrudBundle\Unit;

use App\Entity\User;
use Ewll\CrudBundle\Form\FormConfig;
use Ewll\CrudBundle\Source\DbSource;
use Ewll\CrudBundle\UserProvider\Exception\NoUserException;
use Ewll\CrudBundle\UserProvider\UserProviderInterface;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\Authenticator\Authenticator;

abstract class UnitAbstract implements UnitInterface
{
    /** @var UserProviderInterface */
    protected $userProvider;
    protected $repositoryProvider;
    /** @deprecated Use userProvider */
    protected $authenticator;

    public function __construct(RepositoryProvider $repositoryProvider, Authenticator $authenticator)
    {
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
    }

    public function setUserProvider(UserProviderInterface $userProvider): void
    {
        $this->userProvider = $userProvider;
    }

    public function getUser(): User
    {
        if (null === $this->userProvider) {
            throw new \RuntimeException('userProvider isn\'t set');
        }

        try {
            $user = $this->userProvider->getUser();

            return $user;
        } catch (NoUserException $e) {
            throw new \LogicException('User must be here');
        }
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

    public function isForceDelete(): bool
    {
        return false;
    }

    public function onDelete(object $entity): void
    {
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
