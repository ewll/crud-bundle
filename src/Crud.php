<?php namespace Ewll\CrudBundle;

use Ewll\CrudBundle\Action\ActionInterface;
use Ewll\CrudBundle\Action\CustomAction;
use Ewll\CrudBundle\Exception\AccessConditionException;
use Ewll\CrudBundle\Exception\AccessNotGrantedException;
use Ewll\CrudBundle\Exception\CsrfException;
use Ewll\CrudBundle\Exception\EntityNotFoundException;
use Ewll\CrudBundle\Exception\FilterNotAllowedException;
use Ewll\CrudBundle\Exception\PropertyNotAllowedException;
use Ewll\CrudBundle\Exception\PropertyNotExistsException;
use Ewll\CrudBundle\Exception\SortNotAllowedException;
use Ewll\CrudBundle\Exception\UnitMethodNotAllowedException;
use Ewll\CrudBundle\Exception\UnitNotExistsException;
use Ewll\CrudBundle\Exception\UserNotAuthorizedException;
use Ewll\CrudBundle\Exception\ValidationException;
use Ewll\CrudBundle\Form\FormErrorCompiler;
use Ewll\CrudBundle\Form\FormFactory;
use Ewll\CrudBundle\Preformation\Preformator;
use Ewll\CrudBundle\ReadViewCompiler\ReadViewCompiler;
use Ewll\CrudBundle\Unit\CreateMethodInterface;
use Ewll\CrudBundle\Unit\CustomActionInterface;
use Ewll\CrudBundle\Unit\CustomActionMultipleInterface;
use Ewll\CrudBundle\Unit\CustomActionTargetInterface;
use Ewll\CrudBundle\Unit\CustomActionWithFormInterface;
use Ewll\CrudBundle\Unit\DeleteMethodInterface;
use Ewll\CrudBundle\Unit\ReadMethodInterface;
use Ewll\CrudBundle\Unit\UnitInterface;
use Ewll\CrudBundle\Unit\UpdateMethodInterface;
use Ewll\DBBundle\Repository\Repository;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\AccessRule\AccessChecker;
use Ewll\UserBundle\AccessRule\AccessRuleProvider;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;
use LogicException;
use RuntimeException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Crud
{
    const CSRF_METHODS = [
        ActionInterface::CREATE,
        ActionInterface::UPDATE,
        ActionInterface::DELETE,
        ActionInterface::CUSTOM,
    ];
    const FORM_METHODS = [
        ActionInterface::CREATE,
        ActionInterface::UPDATE,
        ActionInterface::DELETE,
        ActionInterface::CUSTOM,
    ];

    const CONSTRAINT_NAME_ENTITY = 'globalEntity';

    private $validator;
    private $repositoryProvider;
    private $accessRuleProvider;
    private $accessChecker;
    private $authenticator;
    private $readViewCompiler;
    private $preformator;
    private $formErrorCompiler;
    private $formFactory;
    /** @var UnitInterface[] */
    private $crudUnits;
    /** @var CustomActionInterface[] */
    private $crudUnitCustomActions;

    public function __construct(
        ValidatorInterface $validator,
        RepositoryProvider $repositoryProvider,
        AccessRuleProvider $accessRuleProvider,
        AccessChecker $accessChecker,
        Authenticator $authenticator,
        ReadViewCompiler $readViewCompiler,
        Preformator $preformator,
        FormErrorCompiler $formErrorCompiler,
        FormFactory $formFactory,
        iterable $crudUnits,
        iterable $crudUnitCustomActions
    ) {
        $this->validator = $validator;
        $this->repositoryProvider = $repositoryProvider;
        $this->accessRuleProvider = $accessRuleProvider;
        $this->accessChecker = $accessChecker;
        $this->authenticator = $authenticator;
        $this->readViewCompiler = $readViewCompiler;
        $this->preformator = $preformator;
        $this->formErrorCompiler = $formErrorCompiler;
        $this->formFactory = $formFactory;
        $this->crudUnits = $crudUnits;
        $this->crudUnitCustomActions = $crudUnitCustomActions;
    }

    /**
     * @throws UnitNotExistsException
     * @throws UnitMethodNotAllowedException
     * @throws EntityNotFoundException
     * @throws FilterNotAllowedException
     * @throws SortNotAllowedException
     * @throws PropertyNotExistsException
     * @throws PropertyNotAllowedException
     * @throws ValidationException
     * @throws AccessNotGrantedException
     * @throws CsrfException
     * @throws UserNotAuthorizedException
     * @throws AccessConditionException
     */
    public function handle(ActionInterface $action): array
    {
        $unit = $this->getUnit($action->getUnitName());
        $accessRuleClassName = $unit->getAccessRuleClassName();
        $data = $action->getData();
        $user = null;
        if (null !== $accessRuleClassName) {
            $accessRule = $this->accessRuleProvider->findByClassName($accessRuleClassName);
            try {
                $user = $this->authenticator->getUser();
            } catch (NotAuthorizedException $e) {
                throw new UserNotAuthorizedException();
            }
            if (!$this->accessChecker->isGranted($accessRule, $user)) {
                throw new AccessNotGrantedException();
            }
        }
        if (null !== $user && in_array($action->getMethodName(), self::CSRF_METHODS, true)) {
            $token = $data['_token'] ?? null;
            if ($token !== $user->token->data['csrf']) {
                throw new CsrfException();
            }
        }
        $this->checkMethodAllowed($action, $unit);
        $entityClass = $unit->getEntityClass();
        $repository = $this->repositoryProvider->get($entityClass);
        $function = "{$action->getMethodName()}Method";
        if (in_array($action->getMethodName(), self::FORM_METHODS, true)) {
            $data = $data['form'] ?? [];
        }
        if ($action instanceof CustomAction) {
            $response = $this
                ->$function($unit, $repository, $data, $action->getCustomActionName(), $action->getId());
        } else {
            $response = $this->$function($unit, $repository, $data, $action->getId());
        }

        return $response;
    }

    /**
     * @throws EntityNotFoundException
     * @throws ValidationException
     */
    private function customMethod(
        UnitInterface $unit,
        Repository $repository,
        array $data,
        string $customActionName,
        int $id = null
    ): array {
        $customAction = $this->findCustomAction($unit, $customActionName);
        if (null === $customAction) {
            throw new LogicException('Custom action must be found here');
        }
        if ($customAction instanceof CustomActionTargetInterface) {
            $entity = $this->getEntityById($unit, $repository, $id);
            $result = $customAction->action($entity, $data);
        } elseif ($customAction instanceof CustomActionMultipleInterface) {
            $result = $customAction->action($data);
        } else {
            throw new RuntimeException('Unknown CustomAction type');
        }

        return $result;
    }

    /**
     * @throws EntityNotFoundException
     * @throws UnitMethodNotAllowedException
     */
    private function formCustomMethod(
        UnitInterface $unit,
        Repository $repository,
        array $data = null,
        string $customActionName,
        int $id
    ): array {
        $customAction = $this->findCustomAction($unit, $customActionName);
        if (null === $customAction) {
            throw new LogicException('Custom action must be found here');
        }
        if (!$customAction instanceof CustomActionWithFormInterface) {
            throw new UnitMethodNotAllowedException($customActionName);
        }
        $item = $this->getEntityById($unit, $repository, $id);
        $form = $this->formFactory->create($customAction->getFormConfig($item), $item);
        $formDefinition = $this->compileFormDefinition($form);

        return $formDefinition;
    }

    private function formCreateMethod(
        CreateMethodInterface $unit,
        Repository $repository
    ): array {
        $form = $this->formFactory->create($unit->getCreateFormConfig());
//        $hasPreformation = $unit->hasPreformation();
//        if ($hasPreformation) {
//            $this->preformator->fillPreformBuilder($unit, $formBuilder);
//        } else {
//        $unit->fillCreateFormBuilder($formBuilder);
//        }
        $formDefinition = $this->compileFormDefinition($form);
//        $view = $form->createView();
//        $formDefinition = ['fields' => []];
//        foreach ($view as $fieldName => $field) {
//            $formDefinition['fields'][$fieldName] = [];
//        }

        return $formDefinition;
    }

    /** @throws EntityNotFoundException */
    private function formUpdateMethod(
        UpdateMethodInterface $unit,
        Repository $repository,
        array $data = null,
        int $id
    ): array {
        $item = $this->getEntityById($unit, $repository, $id);
        $form = $this->formFactory->create($unit->getUpdateFormConfig($item), $item);
//        $hasPreformation = $unit->hasPreformation();
//        if ($hasPreformation) {
//            $preformData = (array)$item;
//            $this->preformator->fillPreformBuilder($unit, $formBuilder, $preformData);
//        } else {
//            $unit->fillUpdateFormBuilder($formBuilder);
//        }
        $formDefinition = $this->compileFormDefinition($form);

//        if ($hasPreformation) {
//            $parameters = $this->preformator->reverse($unit, $item);
//        } else {
//            $parameters = [];
//            foreach ($formDefinition['data'] as $fieldName => $field) {
//                $parameters[$fieldName] = $view[$fieldName]->vars['value'];
//            }
//        }
//        foreach ($formDefinition['data'] as $fieldName => &$field) {
//            $field = $parameters[$fieldName];
//        }

        return $formDefinition;
    }

    /**
     * @throws EntityNotFoundException
     * @throws ValidationException
     * @noinspection PhpOptionalBeforeRequiredParametersInspection
     */
    private function deleteMethod(
        DeleteMethodInterface $unit,
        Repository $repository,
        array $data = null,
        int $id
    ): array {
        $item = $this->getEntityById($unit, $repository, $id);
        $constraints = $unit->getDeleteConstraints();
        $item->isDeleted = 1;
        $this->validateEntity($item, $constraints);
        $repository->update($item, ['isDeleted']);

        return [];
    }

    /**
     * @throws PropertyNotExistsException
     * @throws PropertyNotAllowedException
     * @throws ValidationException
     * @throws AccessConditionException
     */
    private function createMethod(
        CreateMethodInterface $unit,
        Repository $repository,
        array $properties
    ): array {
//        if ($unit->hasPreformation()) {
//            $properties = $this->preformator->preformate($unit, $properties);
//        }
        $entityClass = $unit->getEntityClass();
        $entity = new $entityClass();
        $form = $this->formFactory->create($unit->getCreateFormConfig(), $entity);
        $form->submit($properties);
        $this->validateForm($form);
        $data = $form->getData();

//        $fieldNames = $this->getEnabledMappedFieldNamesFromForm($form);
//        $this->fillEntity($entity, $entityClass, $fieldNames, $data);
        $mutations = $unit->getMutationsOnCreate($entity);
        foreach ($mutations as $mutationName => $mutationValue) {
            $entity->$mutationName = $mutationValue;
        }

        $accessConditions = $unit->getAccessConditions();
        foreach ($accessConditions as $field => $value) {
            $accessGranted = is_array($value) ? in_array($entity->$field, $value, true) : $entity->$field === $value;
            if (!$accessGranted) {
                throw new AccessConditionException();
            }
        }

        $repository->create($entity);

        return ['id' => $entity->id];
    }

    /**
     * @throws EntityNotFoundException
     * @throws PropertyNotExistsException
     * @throws PropertyNotAllowedException
     * @throws ValidationException
     */
    private function updateMethod(
        UpdateMethodInterface $unit,
        Repository $repository,
        array $properties,
        int $id
    ): array {
//        if ($unit->hasPreformation()) {
//            $properties = $this->preformator->preformate($unit, $properties);
//        }
        $entity = $this->getEntityById($unit, $repository, $id);
        $form = $this->formFactory->create($unit->getUpdateFormConfig($entity), $entity);
//        $unit->fillUpdateFormBuilder($formBuilder);
        $form->submit($properties);
        $this->validateForm($form);
//        $data = $form->getData();

//        $entityClass = $unit->getEntityClass();
//        $entity = $this->getEntityById($unit, $repository, $id);
        $fieldNames = $this->getEnabledMappedFieldNamesFromForm($form);
//        $this->fillEntity($entity, $entityClass, $fieldNames, $data);
        $mutations = $unit->getMutationsOnUpdate($entity);
        foreach ($mutations as $mutationName => $mutationValue) {
            $entity->$mutationName = $mutationValue;
        }
//        $propertyKeys = array_merge(array_keys($data), array_keys($mutations));
        $propertyKeys = array_merge($fieldNames, array_keys($mutations));
        $repository->update($entity, $propertyKeys);

        return [];
    }

    /**
     * @throws EntityNotFoundException
     * @throws FilterNotAllowedException
     * @throws SortNotAllowedException
     */
    private function readMethod(
        ReadMethodInterface $unit,
        Repository $repository,
        array $data = null,
        int $id = null
    ): array {
        if (null !== $id) {
            $response = $this->readOne($unit, $repository, $id);
        } else {
            $response = $this->readList($unit, $repository, $data);
        }

        return $response;
    }

    /** @throws EntityNotFoundException */
    private function readOne(ReadMethodInterface $unit, Repository $repository, int $id): array
    {
        $filters = ['id' => $id, 'isDeleted' => 0];
        $filters = array_merge($filters, $unit->getReadOnePreFilters(), $unit->getAccessConditions());
        $item = $repository->findOneBy($filters);
        if (null === $item) {
            throw new EntityNotFoundException();
        }
        $fields = $unit->getReadOneFields();
        $view = $this->readViewCompiler->compile($item, $fields);

        return $view;
    }

    /**
     * @throws FilterNotAllowedException
     * @throws SortNotAllowedException
     */
    private function readList(ReadMethodInterface $unit, Repository $repository, array $data): array
    {
        $filters = ['isDeleted' => 0];
        $filters = array_merge(
            $filters,
            $unit->getReadListPreFilters(),
            $this->getFilters($unit, $data),
            $unit->getAccessConditions()
        );
        $sort = $this->getSort($unit, $data);
        //@TODO validate 'page' and 'itemsPerPage'
        $page = (int)$data['page'];
        $page = $page > 0 ? $page : 1;
        $itemsPerPage = (int)$data['itemsPerPage'];
        if ($itemsPerPage > 50 || $itemsPerPage < 1) {
            $itemsPerPage = 10;
        }
//        $specialRepositoryMethodName = "crudReadMany$unit";
//        $repositoryMethod = is_callable([$repository, $specialRepositoryMethodName])
//            ? $specialRepositoryMethodName
//            : 'findBy';
        $items = $repository->findBy($filters, null, $page, $itemsPerPage, $sort);
        $total = $repository->getFoundRows();
        $fields = $unit->getReadListFields();
        $views = $this->readViewCompiler->compileList($items, $fields);

        $response = [
            'items' => $views,
            'total' => $total,
        ];

        return $response;
    }

    /** @throws UnitNotExistsException */
    private function getUnit(string $unitName): UnitInterface
    {
        foreach ($this->crudUnits as $unit) {
            if ($unitName === $unit->getUnitName()) {
                return $unit;
            }
        }

        throw new UnitNotExistsException();
    }

    /**
     * @throws PropertyNotExistsException
     * @throws PropertyNotAllowedException
     */
//    private function fillEntity($entity, string $entityClass, array $allowedProperties, array $properties)
//    {
//        foreach ($properties as $propertyName => $propertyValue) {
//            if (!property_exists($entityClass, $propertyName)) {
//                throw new PropertyNotExistsException($propertyName);
//            }
//            if (!in_array($propertyName, $allowedProperties, true)) {
//                throw new PropertyNotAllowedException($propertyName);
//            }
//            $entity->$propertyName = $propertyValue;
//        }
//    }

    /**
     * @throws ValidationException
     */
    private function validateEntity($entity, array $constraintFields)
    {
        $errors = [];
        foreach ($constraintFields as $fieldName => $fieldConstraints) {
            /** @var ConstraintViolation[] $violations */
            if ($fieldName === self::CONSTRAINT_NAME_ENTITY) {
                $violations = $this->validator->validate($entity, $fieldConstraints);
                $errorKey = 'commonError';
            } else {
                $violations = $this->validator->validate($entity->$fieldName, $fieldConstraints);
                $errorKey = $fieldName;
            }
            if (count($violations) > 0) {
                $errors[$errorKey] = $violations[0]->getMessage();
            }
        }
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateForm(FormInterface $form)
    {
        if (!$form->isValid()) {
            $errors = $this->formErrorCompiler->compile($form);
            throw new ValidationException($errors);
        }
    }

    /** @throws FilterNotAllowedException */
    private function getFilters(ReadMethodInterface $unit, array $data)
    {
        $filters = [];
        foreach ($data as $key => $value) {
            if (preg_match('/f_(.+)/', $key, $matches)) {
                $filters[$matches[1]] = $value;
            }
        }
        foreach ($filters as $filterName => $filterValue) {
            if (!in_array($filterName, $unit->getAllowedFilterFields(), true)) {
                throw new FilterNotAllowedException($filterName);
            }
        }

        return $filters;
    }

    /** @throws SortNotAllowedException */
    private function getSort(ReadMethodInterface $unit, array $data)
    {
        foreach ($data as $key => $value) {
            if (preg_match('/s_(.+)/', $key, $matches)) {
                $sort[] = [
                    'type' => Repository::SORT_TYPE_SIMPLE,
                    'field' => $matches[1],
                    'method' => $value,
                ];
            }
        }
        foreach ($sort as $item) {
            if (!in_array($item['field'], $unit->getAllowedSortFields(), true)) {
                throw new SortNotAllowedException($item['field']);
            }
        }
        $sort = array_merge($unit->getPreSort(), $sort);

        return $sort;
    }

    /** @throws UnitMethodNotAllowedException */
    private function checkMethodAllowed(ActionInterface $action, UnitInterface $unit): void
    {
        $methodName = $action->getMethodName();
        if ($action instanceof CustomAction) {
            if (null === $this->findCustomAction($unit, $action->getCustomActionName())) {
                throw new UnitMethodNotAllowedException($action->getCustomActionName());
            }
        } else {
            $methodInterfaceRelation = [
                ActionInterface::READ => ReadMethodInterface::class,
                ActionInterface::CREATE => CreateMethodInterface::class,
                ActionInterface::UPDATE => UpdateMethodInterface::class,
                ActionInterface::DELETE => DeleteMethodInterface::class,
                ActionInterface::FORM_CREATE => CreateMethodInterface::class,
                ActionInterface::FORM_UPDATE => UpdateMethodInterface::class,
            ];
            if (!$unit instanceof $methodInterfaceRelation[$methodName]) {
                throw new UnitMethodNotAllowedException($methodName);
            }
        }
    }

    private function getEnabledMappedFieldNamesFromForm(FormInterface $formBuilder): array
    {
        $fieldNames = [];
        /** @var FormInterface[] $fields */
        $fields = $formBuilder->all();
        foreach ($fields as $field) {
            $config = $field->getConfig();
            $isDisabled = $config->getDisabled();
            $isMapped = $config->getMapped();
            if (!$isDisabled && $isMapped) {
                $fieldNames[] = $field->getName();
            }
        }

        return $fieldNames;
    }

    /** @throws EntityNotFoundException */
    private function getEntityById(UnitInterface $unit, Repository $repository, int $id)
    {
        $filters = ['id' => $id, 'isDeleted' => 0];
        $filters = array_merge($filters, $unit->getAccessConditions());
        $entity = $repository->findOneBy($filters);
        if (null === $entity) {
            throw new EntityNotFoundException();
        }

        return $entity;
    }

    private function findCustomAction(UnitInterface $unit, string $customActionName): ?CustomActionInterface
    {
        foreach ($this->crudUnitCustomActions as $customAction) {
            $isUnitNameMatch = $customAction->getUnitName() === $unit->getUnitName();
            $isActionNameMatch = $customAction->getName() === $customActionName;
            if ($isUnitNameMatch && $isActionNameMatch) {
                foreach ($unit->getCustomActions() as $allowedCustomAction) {
                    if ($customAction instanceof $allowedCustomAction) {
                        return $customAction;
                    }
                }
            }
        }

        return null;
    }

    private function compileFormDefinition(FormInterface $form): array
    {
        $view = $form->createView();
        $formDefinition = ['fields' => [], 'data' => []];
        foreach ($view as $fieldName => $field) {
            list($value, $definition) = $this->compileFormFieldView($field);
            $formDefinition['fields'][$fieldName] = $definition;
            $formDefinition['data'][$fieldName] = $value;
        }

        return $formDefinition;
    }

    private function compileFormFieldView(FormView $field)
    {
        $type = $field->vars['block_prefixes'][1];
        $definition = ['type' => $type, 'constraints' => []];

        $constraints = $field->vars['errors']->getForm()->getConfig()->getOptions()['constraints'];
        foreach ($constraints as $constraint) {
            $constraintClass = get_class($constraint);
            $constraintName = substr(strrchr($constraintClass, '\\'), 1);
            $constraintOptions = [];
            switch($constraintClass) {
                case Count::class:
                    $constraintOptions['min'] = $constraint->min;
                    $constraintOptions['max'] = $constraint->max;
                    break;
            }
            $definition['constraints'][$constraintName] = $constraintOptions;
        }
        switch ($type) {
            case 'collection':
                $value = [];
                $definition['children'] = [];
                foreach ($field as $key => $children) {
                    foreach ($children as $childName => $child) {
                        list($childValue, $childDefinition) = $this->compileFormFieldView($child);
                        $value[$key][$childName] = $childValue;
                        $definition['children'][$key][$childName] = $childDefinition;
                    }
                }
                break;
            case 'choice':
                $value = $field->vars['value'];
                $choices = [];
                foreach ($field->vars['choices'] as $choice) {
                    $choices[] = ['text' => $choice->label, 'value' => $choice->value];
                }
                $definition['choices'] = $choices;
                break;
            default:
                $value = $field->vars['value'];
        }

        return [$value, $definition];
    }
}
