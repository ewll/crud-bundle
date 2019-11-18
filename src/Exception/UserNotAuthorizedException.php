<?php namespace Ewll\CrudBundle\Exception;

use Exception;

class UserNotAuthorizedException extends Exception
{
    public function __construct()
    {
        parent::__construct('User not authorized');
    }
}
