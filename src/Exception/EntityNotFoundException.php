<?php namespace Ewll\CrudBundle\Exception;

use Exception;

class EntityNotFoundException extends Exception
{
    public function __construct()
    {
        parent::__construct('Entity not found');
    }
}
