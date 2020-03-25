<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

class Date extends TransformerInitializerAbstract
{
    const FORMAT_DATE_TIME = 'Y-m-d H:i';
    const FORMAT_DATE = 'Y-m-d';
    const FORMAT_SHORT_DATE_TIME = 'm-d H:i';

    private $format;

    public function __construct(string $fieldName, string $format)
    {
        parent::__construct($fieldName);
        $this->format = $format;
    }

    public function getFormat(): string
    {
        return $this->format;
    }
}
