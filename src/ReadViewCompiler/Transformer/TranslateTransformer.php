<?php namespace Ewll\CrudBundle\ReadViewCompiler\Transformer;

use Ewll\CrudBundle\ReadViewCompiler\Context;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslateTransformer implements ViewTransformerInterface
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function transform(ViewTransformerInitializerInterface $initializer, $item, Context $context = null)
    {
        if (!$initializer instanceof Translate) {
            throw new RuntimeException("Expected '".Translate::class."', got '".get_class($initializer)."'");
        }

        $fieldName = $initializer->getFieldName();
        $field = $item->$fieldName;

        if (null === $field) {
            return null;
        }

        $placeholder = str_replace(Translate::PLACEHOLDER, $field, $initializer->getPlaceholder());
        $result = $this->translator->trans($placeholder, [], $initializer->getDomain());

        return $result;
    }
}
