<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

class Translate extends TransformerInitializerAbstract
{
    const PLACEHOLDER = '{:}';

    private $placeholder;
    private $domain;

    public function __construct(string $fieldName, string $domain, string $placeholder)
    {
        parent::__construct($fieldName);
        $this->domain = $domain;
        $this->placeholder = $placeholder;
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }
}
