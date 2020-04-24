<?php namespace Ewll\CrudBundle\UserProvider;

use App\Entity\User;
use Ewll\CrudBundle\UserProvider\Exception\NoUserException;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;

class AuthenticatorUserProvider implements UserProviderInterface
{
    private $authenticator;

    public function __construct(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    /** @inheritDoc */
    public function getUser(): User
    {
        try {
            $user = $this->authenticator->getUser();

            return $user;
        } catch (NotAuthorizedException $e) {
            throw new NoUserException('', 0, $e);
        }
    }
}
