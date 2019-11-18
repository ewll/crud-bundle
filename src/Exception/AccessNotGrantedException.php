<?php namespace Ewll\CrudBundle\Exception;

use Exception;

class AccessNotGrantedException extends Exception
{
    public function __construct()
    {
        parent::__construct('Access not granted');
    }
}
