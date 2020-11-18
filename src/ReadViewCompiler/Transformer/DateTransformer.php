<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

use Ewll\CrudBundle\ReadViewCompiler\Context;
use DateTime;
use RuntimeException;

class DateTransformer implements ViewTransformerInterface
{
    public function transform(
        ViewTransformerInitializerInterface $initializer,
        $item,
        array $transformMap,
        Context $context = null
    ) {
        if (!$initializer instanceof Date) {
            throw new RuntimeException("Expected '" . Date::class . "', got '" . get_class($initializer) . "'");
        }

        $fieldName = $initializer->getFieldName();
        $field = $item->$fieldName;
        if (null === $field) {
            return null;
        } elseif (!$field instanceof DateTime) {
            throw new RuntimeException("Expected '" . DateTime::class . "', got '" . get_class($field) . "'");
        }

//        try {
//            $user = $this->authenticator->getUser();
//            $timeZone = new \DateTimeZone($user->timezone);
//            $field->setTimezone($timeZone);
//        } catch (NotAuthorizedException $e) {
//        }

        $value = $field->format($initializer->getFormat());

        return $value;
    }
}
