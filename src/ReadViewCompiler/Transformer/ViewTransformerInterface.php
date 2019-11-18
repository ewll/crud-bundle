<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

use Ewll\CrudBundle\ReadViewCompiler\Context;

interface ViewTransformerInterface
{
    public function transform(ViewTransformerInitializerInterface $initializer, $item, Context $context = null);
}
