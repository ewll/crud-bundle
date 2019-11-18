<?php namespace Ewll\CrudBundle\Exception;

use Exception;

class FilterNotAllowedException extends Exception
{
    public function __construct(string $filter)
    {
        parent::__construct("Filter '$filter' not allowed");
    }
}
