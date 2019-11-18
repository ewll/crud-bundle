<?php namespace Ewll\CrudBundle\Exception;

use Exception;

class SortNotAllowedException extends Exception
{
    public function __construct(string $field)
    {
        parent::__construct("Sort '$field' not allowed");
    }
}
