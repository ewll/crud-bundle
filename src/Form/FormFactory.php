<?php namespace Ewll\CrudBundle\Form;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

class FormFactory
{
    private $baseFormFactory;

    public function __construct(
        FormFactoryInterface $baseFormFactory
    ) {
        $this->baseFormFactory = $baseFormFactory;
    }

    public function create($formConfig, $data = null): FormInterface
    {
        if ($formConfig instanceof FormConfig) {
            $fields = $formConfig->getFields();
        } else {
            $fields = $formConfig['fields'];
        }
        $builder = $this->baseFormFactory->createBuilder(FormType::class, $data);
        foreach ($fields as $fieldName => $field) {
            $builder->add($fieldName, $field['class'], $field['options']);
            if (!empty($field['viewTransformer'])) {
                $builder->get($fieldName)->addViewTransformer($field['viewTransformer']);
            }
        }
        $form = $builder->getForm();

        return $form;
    }
}
