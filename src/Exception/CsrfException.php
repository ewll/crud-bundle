<?php namespace Ewll\CrudBundle\Exception;

use Exception;

class CsrfException extends Exception
{
    public function __construct()
    {
        parent::__construct('CSRF-token not exists or invalid.');
    }
}
