<?php namespace Ewll\CrudBundle\UserProvider;

use Ewll\CrudBundle\UserProvider\Exception\NoUserException;

interface UserProviderInterface
{
    /** @throws NoUserException */
    public function getUser(): UserInterface;
}
