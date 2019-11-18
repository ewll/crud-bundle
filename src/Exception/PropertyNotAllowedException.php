<?php namespace Ewll\CrudBundle\Exception;

use Exception;

class PropertyNotAllowedException extends Exception
{
    public function __construct($propertyName)
    {
        parent::__construct("Property not allowed: '$propertyName'");
    }
}
