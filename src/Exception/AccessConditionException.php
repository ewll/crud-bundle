<?php namespace Ewll\CrudBundle\Exception;

use Exception;

class AccessConditionException extends Exception
{
    public function __construct()
    {
        parent::__construct('Access condition problem');
    }
}
