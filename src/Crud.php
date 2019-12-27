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
use Ewll\CrudBundle\AccessCondition;
use Ewll\CrudBundle\Unit\CreateMethodInterface;
use Ewll\CrudBundle\Unit\CustomActionInterface;
use Ewll\CrudBundle\Unit\CustomActionMultipleInterface;
use Ewll\CrudBundle\Unit\CustomActionTargetInterface;
use Ewll\CrudBundle\Unit\CustomActionWithFormInterface;
use Ewll\CrudBundle\Unit\DeleteMethodInterface;
use Ewll\CrudBundle\Unit\ReadMethodInterface;
use Ewll\CrudBundle\Unit\UnitInterface;
use Ewll\CrudBundle\Unit\UpdateMethodInterface;
use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Query\QueryBuilder;
use Ewll\DBBundle\Repository\FilterExpression;
use Ewll\DBBundle\Repository\Repository;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Ewll\UserBundle\AccessRule\AccessChecker;
use Ewll\UserBundle\AccessRule\AccessRuleProvider;
use Ewll\UserBundle\Authenticator\Authenticator;
use Ewll\UserBundle\Authenticator\Exception\NotAuthorizedException;
use Exception;
use LogicException;
use RuntimeException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
    private $defaultDbClient;
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
    private $translator;

    public function __construct(
        ValidatorInterface $validator,
        RepositoryProvider $repositoryProvider,
        DbClient $defaultDbClient,
        AccessRuleProvider $accessRuleProvider,
        AccessChecker $accessChecker,
        Authenticator $authenticator,
        ReadViewCompiler $readViewCompiler,
        Preformator $preformator,
        FormErrorCompiler $formErrorCompiler,
        FormFactory $formFactory,
        iterable $crudUnits,
        iterable $crudUnitCustomActions,
        TranslatorInterface $translator
    )
    {
        $this->validator = $validator;
        $this->repositoryProvider = $repositoryProvider;
        $this->defaultDbClient = $defaultDbClient;
        $this->accessRuleProvider = $accessRuleProvider;
        $this->accessChecker = $accessChecker;
        $this->authenticator = $authenticator;
        $this->readViewCompiler = $readViewCompiler;
        $this->preformator = $preformator;
        $this->formErrorCompiler = $formErrorCompiler;
        $this->formFactory = $formFactory;
        $this->crudUnits = $crudUnits;
        $this->crudUnitCustomActions = $crudUnitCustomActions;
        $this->translator = $translator;
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
     * @throws AccessConditionException
     */
    private function customMethod(
        UnitInterface $unit,
        Repository $repository,
        array $data,
        string $customActionName,
        int $id = null
    ): array
    {
        $customAction = $this->findCustomAction($unit, $customActionName);
        if (null === $customAction) {
            throw new LogicException('Custom action must be found here');
        }
        if ($customAction instanceof CustomActionTargetInterface) {
            $accessConditions = $unit->getAccessConditions(ActionInterface::CUSTOM);
            $entity = $this->getEntityById($repository, $accessConditions, $id);
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
     * @throws AccessConditionException
     */
    private function formCustomMethod(
        UnitInterface $unit,
        Repository $repository,
        array $data = null,
        string $customActionName,
        int $id
    ): array
    {
        $customAction = $this->findCustomAction($unit, $customActionName);
        if (null === $customAction) {
            throw new LogicException('Custom action must be found here');
        }
        if (!$customAction instanceof CustomActionWithFormInterface) {
            throw new UnitMethodNotAllowedException($customActionName);
        }
        $accessConditions = $unit->getAccessConditions(ActionInterface::FORM_CUSTOM);
        $item = $this->getEntityById($repository, $accessConditions, $id);
        $form = $this->formFactory->create($customAction->getFormConfig($item), $item);
        $formDefinition = $this->compileFormDefinition($form);

        return $formDefinition;
    }

    private function formCreateMethod(
        CreateMethodInterface $unit,
        Repository $repository
    ): array
    {
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

    /**
     * @throws EntityNotFoundException
     * @throws AccessConditionException
     */
    private function formUpdateMethod(
        UpdateMethodInterface $unit,
        Repository $repository,
        array $data = null,
        int $id
    ): array
    {
        $accessConditions = $unit->getAccessConditions(ActionInterface::FORM_UPDATE);
        $item = $this->getEntityById($repository, $accessConditions, $id);
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
     * @throws AccessConditionException
     * @noinspection PhpOptionalBeforeRequiredParametersInspection
     */
    private function deleteMethod(
        DeleteMethodInterface $unit,
        Repository $repository,
        array $data = null,
        int $id
    ): array
    {
        $accessConditions = $unit->getAccessConditions(ActionInterface::DELETE);
        $item = $this->getEntityById($repository, $accessConditions, $id);
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
    ): array
    {
//        if ($unit->hasPreformation()) {
//            $properties = $this->preformator->preformate($unit, $properties);
//        }
        $entityClass = $unit->getEntityClass();
        $entity = new $entityClass();
        $form = $this->formFactory->create($unit->getCreateFormConfig(), $entity);
        $form->submit($properties);
        $this->validateForm($form);
//        $data = $form->getData();

//        $fieldNames = $this->getEnabledMappedFieldNamesFromForm($form);
//        $this->fillEntity($entity, $entityClass, $fieldNames, $data);
        $mutations = $unit->getMutationsOnCreate($entity);
        foreach ($mutations as $mutationName => $mutationValue) {
            $entity->$mutationName = $mutationValue;
        }

        $accessConditions = $unit->getAccessConditions(ActionInterface::CREATE);
        $this->checkEntityAccess($accessConditions, $entity);

        $this->defaultDbClient->beginTransaction();
        try {
            $repository->create($entity);
            $unit->onCreate($entity);
            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
        return ['id' => $entity->id];
    }

    /**
     * @throws EntityNotFoundException
     * @throws PropertyNotExistsException
     * @throws PropertyNotAllowedException
     * @throws ValidationException
     * @throws AccessConditionException
     */
    private function updateMethod(
        UpdateMethodInterface $unit,
        Repository $repository,
        array $properties,
        int $id
    ): array
    {
//        if ($unit->hasPreformation()) {
//            $properties = $this->preformator->preformate($unit, $properties);
//        }
        $accessConditions = $unit->getAccessConditions(ActionInterface::UPDATE);
        $entity = $this->getEntityById($repository, $accessConditions, $id);
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

        $this->checkEntityAccess($accessConditions, $entity);
        $propertyKeys = array_merge($fieldNames, array_keys($mutations));
        if (count($propertyKeys) > 0) {
            $repository->update($entity, $propertyKeys);
        }
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
    ): array
    {
        $qb = new QueryBuilder($repository);
        if (null !== $id) {
            $response = $this->readOne($unit, $qb, $repository, $id);
        } else {
            $response = $this->readList($unit, $qb, $repository, $data);
        }

        return $response;
    }

    /** @throws EntityNotFoundException */
    private function readOne(ReadMethodInterface $unit, QueryBuilder $qb, Repository $repository, int $id): array
    {
        $filters = ['id' => $id, 'isDeleted' => 0];
        $accessConditions = $unit->getAccessConditions(ActionInterface::READ);
        $accessConditionFilters = $this->convertAccessConditionToFilterExpression($qb, $accessConditions);
        $filters = array_merge($filters, $unit->getReadOnePreFilters(), $accessConditionFilters);
        $qb
            ->addConditions($filters)
            ->setLimit(1);
        $item = $repository->find($qb);
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
    private function readList(ReadMethodInterface $unit, QueryBuilder $qb, Repository $repository, array $data): array
    {
        $accessConditions = $unit->getAccessConditions(ActionInterface::READ);
        $filters = ['isDeleted' => 0];
        $filters = array_merge(
            $filters,
            $unit->getReadListPreFilters(),
            $this->getFilters($unit, $data),
            $this->convertAccessConditionToFilterExpression($qb, $accessConditions)
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
        $qb
            ->addConditions($filters)
            ->setSort($sort)
            ->setPage($page, $itemsPerPage);
        $items = $repository->find($qb);
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
    private function getEntityById(Repository $repository, array $accessConditions, int $id)
    {
        $qb = new QueryBuilder($repository);
        $filters = ['id' => $id, 'isDeleted' => 0];
        $filters = array_merge($filters, $this->convertAccessConditionToFilterExpression($qb, $accessConditions));
        $qb
            ->addConditions($filters)
            ->setLimit(1);
        $entity = $repository->find($qb);
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
        $definition = ['type' => $type, 'constraints' => [], 'disabled' => $field->vars['disabled']];

        $constraints = $field->vars['errors']->getForm()->getConfig()->getOptions()['constraints'];
        foreach ($constraints as $constraint) {
            $constraintClass = get_class($constraint);
            $constraintName = substr(strrchr($constraintClass, '\\'), 1);
            $constraintOptions = [];
            switch ($constraintClass) {
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
                $translationDomain = $field->vars['choice_translation_domain'];
                $choices = [];
                foreach ($field->vars['choices'] as $choice) {
                    if (null !== $translationDomain) {
                        $text = $this->translator->trans($choice->label, [], $translationDomain);
                    } else {
                        $text = $choice->label;
                    }
                    $choices[] = ['text' => $text, 'value' => $choice->value];
                }
                $definition['choices'] = $choices;
                break;
            default:
                $value = $field->vars['value'];
        }

        return [$value, $definition];
    }

    /**
     * @param AccessCondition\AccessConditionInterface[] $accessConditions
     * @return FilterExpression[]
     */
    private function convertAccessConditionToFilterExpression(QueryBuilder $qb, array $accessConditions)
    {
        $filters = [];
        $acCount = 0;
        foreach ($accessConditions as $accessCondition) {
            $acCount++;
            if ($accessCondition instanceof AccessCondition\ExpressionAccessCondition) {
                $field = $accessCondition->getField();
                $value = $accessCondition->getValue();
                switch ($accessCondition->getAction()) {
                    case AccessCondition\ExpressionAccessCondition::ACTION_EQUAL:
                        $filters[] = new FilterExpression(FilterExpression::ACTION_EQUAL, $field, $value);
                        break;
                    case AccessCondition\ExpressionAccessCondition::ACTION_NOT_EQUAL:
                        $filters[] = new FilterExpression(FilterExpression::ACTION_NOT_EQUAL, $field, $value);
                        break;
                    default:
                        throw new RuntimeException('Unknown ExpressionAccessCondition action');
                }
            } elseif ($accessCondition instanceof AccessCondition\RelationAccessCondition) {
                $joinEntityRepository = $this->repositoryProvider->get($accessCondition->getClassName());
                $tableName = $joinEntityRepository->getEntityConfig()->tableName;
                $prefix = "ac{$acCount}";
                $mainPrefix = $qb->getPrefix();
                $conditions = [];
                foreach ($accessCondition->getConditions() as $field => $condition) {
                    switch ($condition['type']) {
                        case 'field':
                            $value = "{$mainPrefix}.{$condition['value']}";
                            break;
                        case 'value':
                            $value = $condition['value'];
                            break;
                        default:
                            throw new RuntimeException('Unknown RelationAccessCondition field type');
                    }
                    $conditions[] = "{$prefix}.{$field} = {$value}";
                }
                $qb
                    ->addJoin($tableName, $prefix, implode(' AND ', $conditions),'LEFT');
                switch ($accessCondition->getAction()) {
                    case AccessCondition\RelationAccessCondition::COND_RELATE:
                        $filterAction = FilterExpression::ACTION_IS_NOT_NULL;
                        break;
                    case AccessCondition\RelationAccessCondition::COND_NOT_RELATE:
                        $filterAction = FilterExpression::ACTION_IS_NULL;
                        break;
                    default:
                        throw new RuntimeException('Unknown RelationAccessCondition action');
                }
                $qb->addCondition(new FilterExpression($filterAction, [$prefix, 'id']));
            } else {
                throw new RuntimeException('Unknown AccessCondition');
            }
        }

        return $filters;
    }

    /** @throws AccessConditionException */
    private function checkEntityAccess(array $accessConditions, object $entity): void
    {
        foreach ($accessConditions as $accessCondition) {
            if ($accessCondition instanceof AccessCondition\ExpressionAccessCondition) {
                $accessConditionValue = $accessCondition->getValue();
                $isAccessConditionValueArray = is_array($accessConditionValue);
                $accessConditionField = $accessCondition->getField();
                switch ($accessCondition->getAction()) {
                    case AccessCondition\ExpressionAccessCondition::ACTION_EQUAL:
                        $accessGranted = $isAccessConditionValueArray
                            ? in_array($entity->$accessConditionField, $accessConditionValue, true)
                            : $entity->$accessConditionField === $accessConditionValue;
                        break;
                    case AccessCondition\ExpressionAccessCondition::ACTION_NOT_EQUAL:
                        $accessGranted = $isAccessConditionValueArray
                            ? !in_array($entity->$accessConditionField, $accessConditionValue, true)
                            : $entity->$accessConditionField !== $accessConditionValue;
                        break;
                    default:
                        throw new RuntimeException('Unknown ExpressionAccessCondition action');
                }
            } else {
                throw new RuntimeException('Unknown AccessCondition');
            }
            if (!$accessGranted) {
                throw new AccessConditionException();
            }
        }
    }
}
