<?php namespace Ewll\CrudBundle\Form;

class FormConfig
{
    private $fields = [];
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function addField(string $name, string $class, array $options = [], $viewTransformer = null): FormConfig
    {
        $field = [
            'class' => $class,
            'options' => $options,
        ];
        if (null !== $viewTransformer) {
            $field['viewTransformer'] = $viewTransformer;
        }
        $this->fields[$name] = $field;

        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
