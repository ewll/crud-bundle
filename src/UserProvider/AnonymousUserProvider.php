<?php namespace Ewll\CrudBundle\UserProvider;

class AnonymousUserProvider implements UserProviderInterface
{
    /** @inheritDoc */
    public function getUser(): UserInterface
    {
        return new Anonymous();
    }
}
