<?php namespace Ewll\CrudBundle\Form;

use Symfony\Component\Form\FormInterface;

class FormErrorCompiler
{
    public function compile(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $skipError = false;
            $origin = $error->getOrigin();
            if ('form' === $origin->getName()) {
                $errors['form'] = $error->getMessage();
            } else {
                $pathItems = $this->getPathItems($origin);
                $cursor = &$errors;
                foreach ($pathItems as $pathItem) {
                    if (is_string($cursor)) {
                        $skipError = true;
                        break;
                    }
                    if (!isset($cursor[$pathItem])) {
                        $cursor[$pathItem] = null;
                    }
                    $cursor = &$cursor[$pathItem];
                }
                if (!$skipError) {
                    $cursor = $error->getMessage();
                }
            }
        }

        return $errors;
    }

    private function getPathItems(FormInterface $node, $items = []): array
    {
        $parent = $node->getParent();
        if (null === $parent) {
            return $items;
        }
        array_unshift($items, $node->getName());

        return $this->getPathItems($parent, $items);
    }
}
