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

    public function create(FormConfig $formConfig, $data = null): FormInterface
    {
        $builderOptions = [];
        $entityClass = $formConfig->getEntityClass();
        if (null !== $entityClass) {
            $builderOptions['data_class'] = $entityClass;
        }
        $builder = $this->baseFormFactory
            ->createBuilder(FormType::class, $data, $builderOptions);
        foreach ($formConfig->getFields() as $fieldName => $field) {
            $options = $field['options'] ?? [];
            $builder->add($fieldName, $field['class'], $options);
            if (!empty($field['viewTransformer'])) {
                $builder->get($fieldName)->addViewTransformer($field['viewTransformer']);
            }
        }
        $form = $builder->getForm();

        return $form;
    }
}
