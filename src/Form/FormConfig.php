<?php namespace Ewll\CrudBundle\Form;

class FormConfig
{
    private $entityClass;
    private $fields = [];

    public function __construct(string $entityClass = null)
    {
        $this->entityClass = $entityClass;
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

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }
}
