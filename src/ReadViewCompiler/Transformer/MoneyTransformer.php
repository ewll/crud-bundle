<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

use Ewll\CrudBundle\ReadViewCompiler\Context;
use RuntimeException;

class MoneyTransformer implements ViewTransformerInterface
{
    public function __construct()
    {
    }

    public function transform(
        ViewTransformerInitializerInterface $initializer,
        $item,
        array $transformMap,
        Context $context = null
    ) {
        if (!$initializer instanceof Money) {
            throw new RuntimeException("Expected '".Money::class."', got '".get_class($initializer)."'");
        }

        $fieldName = $initializer->getFieldName();
        $field = $item->$fieldName;

        if (null === $field) {
            return null;
        }

        $thousandsSeparator = $initializer->isView() ? ',' : '';
        //@TODO number of decimals depend of currency scale
        $result = number_format($field, 2, '.', $thousandsSeparator);

        return $result;
    }
}
