<?php namespace Ewll\CrudBundle\Form\Extension\Core\Type;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchType extends TextType
{
    /** {@inheritDoc} */
    public function getBlockPrefix()
    {
        return 'search';
    }

    /** {@inheritDoc} */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'entity' => null,
        ]);
        parent::configureOptions($resolver);
    }
}
