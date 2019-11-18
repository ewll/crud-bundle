<?php namespace Ewll\CrudBundle\Exception;

use Exception;

class PropertyNotExistsException extends Exception
{
    public function __construct($propertyName)
    {
        parent::__construct("Property not exists: '$propertyName'");
    }
}
