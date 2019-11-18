<?php namespace Ewll\CrudBundle\Exception;

use Exception;

class UnitMethodNotAllowedException extends Exception
{
    public function __construct(string $method)
    {
        parent::__construct("Unit method '$method' not allowed");
    }
}
