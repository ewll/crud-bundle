<?php namespace Ewll\CrudBundle\Action;

use Ewll\CrudBundle\UserProvider\UserProviderInterface;

abstract class ActionAbstract implements ActionInterface
{
    private $userProvider;
    private $methodName;
    private $unitName;
    private $id;
    private $data;
    private $needToCheckCsrfToken;

    public function __construct(
        UserProviderInterface $userProvider,
        string $methodName,
        string $unitName,
        int $id = null,
        array $data = null,
        bool $needToCheckCsrfToken = ActionInterface::CHECK_CSRF
    ) {
        $this->userProvider = $userProvider;
        $this->methodName = $methodName;
        $this->unitName = $unitName;
        $this->id = $id;
        $this->data = $data;
        $this->needToCheckCsrfToken = $needToCheckCsrfToken;
    }

    public function getUserProvider(): UserProviderInterface
    {
        return $this->userProvider;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getUnitName(): string
    {
        return $this->unitName;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function needToCheckCsrfToken(): bool
    {
        return $this->needToCheckCsrfToken;
    }
}
