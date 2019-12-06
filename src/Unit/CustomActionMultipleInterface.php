<?php namespace Ewll\CrudBundle\Unit;

use Ewll\CrudBundle\Exception\ValidationException;

interface CustomActionMultipleInterface extends CustomActionInterface
{
    /** @throws ValidationException */
    public function action(array $data): array;
}
