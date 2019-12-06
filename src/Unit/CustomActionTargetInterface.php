<?php namespace Ewll\CrudBundle\Unit;

use Ewll\CrudBundle\Exception\ValidationException;

interface CustomActionTargetInterface extends CustomActionInterface
{
    /** @throws ValidationException */
    public function action($entity, array $data): array;
}
