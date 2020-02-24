<?php namespace Ewll\CrudBundle\ReadViewCompiler;

use Ewll\CrudBundle\ReadViewCompiler\Transformer\ViewTransformerInitializerInterface;
use Ewll\CrudBundle\ReadViewCompiler\Transformer\ViewTransformerInterface;
use RuntimeException;

class ReadViewCompiler
{
    /** @var ViewTransformerInterface[] */
    private $transformers;

    public function __construct(
        iterable $transformers
    ) {
        $this->transformers = $transformers;
    }

    public function compile($item, array $fields, Context $context = null)
    {
        $view = [];
        foreach ($fields as $fieldKey => $transformers) {
            $fieldName = is_string($transformers) ? $transformers : $fieldKey;
            if (!is_array($transformers)) {
                $transformers = [$transformers];
            }
            $preview = $item;
            $transformMap = [];
            foreach ($transformers as $transformer) {
                if (null !== $preview) {
                    $preview = $this->transform($transformer, $preview, $transformMap, $context);
                }
            }
            $view[$fieldName] = $preview;
        }

        return $view;
    }

    public function compileList(array $items, array $fields): array
    {
        $context = new Context($items);
        $views = [];
        foreach ($items as $item) {
            $views[] = $this->compile($item, $fields, $context);
        }

        return $views;
    }

    private function getTransformer(ViewTransformerInitializerInterface $initializer): ViewTransformerInterface
    {
        $transformerClassName = get_class($initializer).'Transformer';
        foreach ($this->transformers as $transformer) {
            if (get_class($transformer) === $transformerClassName) {
                return $transformer;
            }
        }

        throw new RuntimeException("Transformer '$transformerClassName' not found");
    }

    private function transform($transformer, $item, array &$transformMap, Context $context = null)
    {
        if (is_string($transformer)) {
            $fieldName = $transformer;
            $view = $item->$fieldName;
        } elseif ($transformer instanceof ViewTransformerInitializerInterface) {
            $viewTransformerInitializer = $transformer;
            $transformMap[] = $viewTransformerInitializer->getFieldName();
            $transformer = $this->getTransformer($viewTransformerInitializer);
            $view = $transformer->transform($viewTransformerInitializer, $item, $transformMap, $context);
        } elseif (is_callable($transformer)) {
            $function = $transformer;
            $view = $function($item);
        } else {
            throw new RuntimeException('Unexpected read view type');
        }

        return $view;
    }
}
