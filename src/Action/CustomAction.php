<?php namespace Ewll\CrudBundle\Action;

use Ewll\CrudBundle\UserProvider\UserProviderInterface;

class CustomAction extends ActionAbstract
{
    private $customActionName;

    public function __construct(
        UserProviderInterface $userProvider,
        string $method,
        string $unitName,
        array $data,
        string $customActionName,
        int $id = null,
        bool $needToCheckCsrfToken = ActionInterface::CHECK_CSRF
    ) {
        $this->customActionName = $customActionName;

        parent::__construct($userProvider, $method, $unitName, $id, $data, $needToCheckCsrfToken);
    }

    public function getCustomActionName(): string
    {
        return $this->customActionName;
    }
}
