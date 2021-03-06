<?php namespace Ewll\CrudBundle\Form\Extension\Core\Type;

use Ewll\CrudBundle\Form\Extension\Core\DataTransformer\VuetifyCheckboxTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class VuetifyCheckboxType extends CheckboxType
{
    const RAW_VALUE_TRUE = 'true';
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setData(isset($options['data']) ? $options['data'] : false);
        $builder->addViewTransformer(new VuetifyCheckboxTransformer($options['value']));
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars = array_replace($view->vars, [
            'value' => $form->getViewData(),
            'checked' => $form->getViewData(),
        ]);
    }
}
