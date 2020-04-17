<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

use Ewll\CrudBundle\ReadViewCompiler\Context;
use DateTime;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;
use RuntimeException;

class DateTransformer implements ViewTransformerInterface
{
    private $authenticator;

    public function __construct(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }

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
        if (!$field instanceof DateTime) {
            throw new RuntimeException("Expected '" . DateTime::class . "', got '" . get_class($field) . "'");
        }

        try {
            $user = $this->authenticator->getUser();
            $timeZone = new \DateTimeZone($user->timezone);
            $field->setTimezone($timeZone);
        } catch (NotAuthorizedException $e) {
        }

        $value = $field->format($initializer->getFormat());

        return $value;
    }
}
