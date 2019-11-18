<?php namespace Ewll\CrudBundle\Preformation;

use Ewll\CrudBundle\Exception\ValidationException;
use Ewll\CrudBundle\Unit\CreateMethodInterface;
use Ewll\CrudBundle\Unit\UnitInterface;
use Ewll\CrudBundle\Unit\UpdateMethodInterface;
use RuntimeException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;

class Preformator
{
    private $formFactory;
    /** @var PreformationInterface[] */
    private $preformations;

    public function __construct(
        FormFactoryInterface $formFactory,
        iterable $preformations
    ) {
        $this->formFactory = $formFactory;
        $this->preformations = $preformations;
    }

    /** @throws ValidationException */
    public function preformate(UnitInterface $unit, array $data): array
    {
        /** @var CreateMethodInterface|UpdateMethodInterface $unit */
        $preformationClassName = $unit->getPreformationClassName();
        $preformation = $this->getPreformation($preformationClassName);
        $formBuilder = $this->formFactory->createBuilder(FormType::class, null, ['csrf_protection' => false]);
        $preformation->fillPreformBuilder($formBuilder, $data);
        $form = $formBuilder->getForm();
        $form->submit($data);
        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[$error->getOrigin()->getName()] = $error->getMessage();
            }
            throw new ValidationException($errors);
        }
        $data = $preformation->transform($form->getData());

        return $data;
    }

    public function fillPreformBuilder(
        UnitInterface $unit,
        FormBuilderInterface $formBuilder,
        $data = []
    ): void {
        /** @var CreateMethodInterface|UpdateMethodInterface $unit */
        $preformationClassName = $unit->getPreformationClassName();
        $preformation = $this->getPreformation($preformationClassName);
        $preformation->fillPreformBuilder($formBuilder, $data);
    }

    public function reverse(UnitInterface $unit, $entity): array
    {
        /** @var CreateMethodInterface|UpdateMethodInterface $unit */
        $preformationClassName = $unit->getPreformationClassName();
        $preformation = $this->getPreformation($preformationClassName);
        $data = $preformation->reverse($entity);

        return $data;
    }

    private function getPreformation(string $className): PreformationInterface
    {
        foreach ($this->preformations as $preformation) {
            if (get_class($preformation) === $className) {
                return $preformation;
            }
        }

        throw new RuntimeException("Transformer '$className' not found");
    }
}
