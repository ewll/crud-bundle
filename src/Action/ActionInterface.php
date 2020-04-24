<?php namespace Ewll\CrudBundle\Action;

use Ewll\CrudBundle\UserProvider\UserProviderInterface;

interface ActionInterface
{
    const CHECK_CSRF = true;
    const NO_CHECK_CSRF = false;

    const CONFIG = 'config';
    const READ = 'read';
    const CREATE = 'create';
    const UPDATE = 'update';
    const CUSTOM = 'custom';
    const DELETE = 'delete';
    const FORM_CREATE = 'formCreate';
    const FORM_UPDATE = 'formUpdate';
    const FORM_CUSTOM = 'formCustom';

    public function getUserProvider(): UserProviderInterface;
    public function needToCheckCsrfToken(): bool;
    public function getMethodName(): string;
    public function getUnitName(): string;
    public function getId(): ?int;
    public function getData(): ?array;
}
