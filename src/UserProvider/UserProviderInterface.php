<?php namespace Ewll\CrudBundle\UserProvider;

use App\Entity\User;
use Ewll\CrudBundle\UserProvider\Exception\NoUserException;

interface UserProviderInterface
{
    /** @throws NoUserException */
    public function getUser(): User;
}
