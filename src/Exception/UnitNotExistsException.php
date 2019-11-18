<?php namespace Ewll\CrudBundle\Exception;

use Exception;

class UnitNotExistsException extends Exception
{
    public function __construct()
    {
        parent::__construct('Unit not exists');
    }
}
