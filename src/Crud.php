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
use Ewll\CrudBundle\Exception\ValidationException;
use Ewll\CrudBundle\Form\Extension\Core\Type\SearchType;
use Ewll\CrudBundle\Form\FormErrorCompiler;
use Ewll\CrudBundle\Form\FormFactory;
use Ewll\CrudBundle\ReadViewCompiler\ReadViewCompiler;
use Ewll\CrudBundle\Condition;
use Ewll\CrudBundle\Source\SourceInterface;
use Ewll\CrudBundle\Unit\CreateMethodInterface;
use Ewll\CrudBundle\Unit\CustomActionInterface;
use Ewll\CrudBundle\Unit\CustomActionMultipleInterface;
use Ewll\CrudBundle\Unit\CustomActionTargetInterface;
use Ewll\CrudBundle\Unit\CustomActionWithFormInterface;
use Ewll\CrudBundle\Unit\DeleteMethodInterface;
use Ewll\CrudBundle\Unit\ReadMethodInterface;
use Ewll\CrudBundle\Unit\UnitInterface;
use Ewll\CrudBundle\Unit\UpdateMethodInterface;
use Ewll\CrudBundle\UserProvider\Exception\NoUserException;
use LogicException;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
    private $readViewCompiler;
    private $formErrorCompiler;
    private $formFactory;
    /** @var UnitInterface[] */
    private $crudUnits;
    /** @var CustomActionInterface[] */
    private $crudUnitCustomActions;
    private $translator;
    private $container;
    /** @var SourceInterface[] */
    private $crudSources;

    public function __construct(
        ValidatorInterface $validator,
        ReadViewCompiler $readViewCompiler,
        FormErrorCompiler $formErrorCompiler,
        FormFactory $formFactory,
        iterable $crudUnits,
        iterable $crudUnitCustomActions,
        TranslatorInterface $translator,
        ContainerInterface $container,
        iterable $crudSources
    ) {
        $this->validator = $validator;
        $this->readViewCompiler = $readViewCompiler;
        $this->formErrorCompiler = $formErrorCompiler;
        $this->formFactory = $formFactory;
        $this->crudUnits = $crudUnits;
        $this->crudUnitCustomActions = $crudUnitCustomActions;
        $this->translator = $translator;
        $this->container = $container;
        $this->crudSources = $crudSources;
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
     * @throws NoUserException
     * @throws AccessConditionException
     */
    public function handle(ActionInterface $action): array
    {
        $userProvider = $action->getUserProvider();
        $unit = $this->getUnit($action->getUnitName());
        $unit->setUserProvider($userProvider);
        $accessRuleClassName = $unit->getAccessRuleClassName();
        $data = $action->getData();
        $user = null;
        if (null !== $accessRuleClassName) {
//            $accessRule = $this->accessRuleProvider->findByClassName($accessRuleClassName);
            $user = $userProvider->getUser();
//            if (!$this->accessChecker->isGranted($accessRule, $user)) {
//                throw new AccessNotGrantedException();
//            }
        }
        $isCsrfMethod = in_array($action->getMethodName(), self::CSRF_METHODS, true);
        //@TODO Token by form
        if (null !== $user && $isCsrfMethod && $action->needToCheckCsrfToken()) {
            $token = $data['_token'] ?? null;
            if ($token !== $user->token->data['csrf']) {
                throw new CsrfException();
            }
        }
        $this->checkMethodAllowed($action, $unit);
        $source = $this->getSource($unit);
        $function = "{$action->getMethodName()}Method";
        if (in_array($action->getMethodName(), self::FORM_METHODS, true)) {
            $data = $data['form'] ?? [];
        }
        if ($action instanceof CustomAction) {
            $response = $this
                ->$function($unit, $source, $data, $action->getCustomActionName(), $action->getId());
        } else {
            $response = $this->$function($unit, $source, $data, $action->getId());
        }

        return $response;
    }

    private function configMethod(UnitInterface $unit)
    {
        $config = [];

        if ($unit instanceof ReadMethodInterface) {
            $config['read'] = [
                'filters' => new \stdClass(),
            ];
            $filtersFormConfig = $unit->getFiltersFormConfig();
            if (null !== $filtersFormConfig) {
                $filtersForm = $this->formFactory->create($unit->getFiltersFormConfig());
                $config['read']['filters'] = $this->compileFormDefinition($filtersForm);
            }
        }
        if ($unit instanceof CreateMethodInterface) {
            $form = $this->formFactory->create($unit->getCreateFormConfig());
            $config['create'] = $this->compileFormDefinition($form);
        }

        return $config;
    }

    /**
     * @throws EntityNotFoundException
     * @throws ValidationException
     * @throws AccessConditionException
     */
    private function customMethod(
        UnitInterface $unit,
        SourceInterface $source,
        array $data,
        string $customActionName,
        int $id = null
    ): array {
        $customAction = $this->findCustomAction($unit, $customActionName);
        if (null === $customAction) {
            throw new LogicException('Custom action must be found here');
        }
        if ($customAction instanceof CustomActionTargetInterface) {
            $accessConditions = $unit->getAccessConditions(ActionInterface::CUSTOM);
            $entity = $source->getById($unit->getEntityClass(), $id, $accessConditions);
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
        SourceInterface $source,
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
        $accessConditions = $unit->getAccessConditions(ActionInterface::FORM_CUSTOM);
        $item = $source->getById($unit->getEntityClass(), $id, $accessConditions);
        $form = $this->formFactory->create($customAction->getFormConfig($item), $item);
        $formDefinition = $this->compileFormDefinition($form);

        return $formDefinition;
    }

    /** @deprecated Use configMethod */
    private function formCreateMethod(CreateMethodInterface $unit): array
    {
        $form = $this->formFactory->create($unit->getCreateFormConfig());
        $formDefinition = $this->compileFormDefinition($form);

        return $formDefinition;
    }

    /**
     * @throws EntityNotFoundException
     * @throws AccessConditionException
     */
    private function formUpdateMethod(
        UpdateMethodInterface $unit,
        SourceInterface $source,
        array $data = null,
        int $id
    ): array {
        $accessConditions = $unit->getAccessConditions(ActionInterface::FORM_UPDATE);
        $item = $source->getById($unit->getEntityClass(), $id, $accessConditions);
        $form = $this->formFactory->create($unit->getUpdateFormConfig($item), $item);
        $formDefinition = $this->compileFormDefinition($form);

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
        SourceInterface $source,
        array $data = null,
        int $id
    ): array {
        $accessConditions = $unit->getAccessConditions(ActionInterface::DELETE);
        $item = $source->getById($unit->getEntityClass(), $id, $accessConditions);
        $constraints = $unit->getDeleteConstraints();
        $this->validateEntity($item, $constraints);
        $source->delete($item, $unit->isForceDelete(), function () use ($unit, $item) {
            $unit->onDelete($item);
        });

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
        SourceInterface $source,
        array $properties
    ): array {
        $entityClass = $unit->getEntityClass();
        $entity = new $entityClass();
        $form = $this->formFactory->create($unit->getCreateFormConfig(), $entity);
        $form->submit($properties);
        $this->validateForm($form);
        $mutations = $unit->getMutationsOnCreate($entity);
        foreach ($mutations as $mutationName => $mutationValue) {
            $entity->$mutationName = $mutationValue;
        }

        $accessConditions = $unit->getAccessConditions(ActionInterface::CREATE);
        $this->checkEntityAccess($source, $accessConditions, $entity);
        $source->create($entity, function () use ($unit, $entity, $properties) {
            $unit->onCreate($entity, $properties);
        });

        return ['id' => $entity->id, 'extra' => $unit->getCreateExtraData($entity)];
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
        SourceInterface $source,
        array $properties,
        int $id
    ): array {
        $accessConditions = $unit->getAccessConditions(ActionInterface::UPDATE);
        $entity = $source->getById($unit->getEntityClass(), $id, $accessConditions);

        $form = $this->formFactory->create($unit->getUpdateFormConfig($entity), $entity);
        $form->submit($properties);
        $this->validateForm($form);
        $fieldNames = $this->getEnabledMappedFieldNamesFromForm($form);
        $mutations = $unit->getMutationsOnUpdate($entity);
        foreach ($mutations as $mutationName => $mutationValue) {
            $entity->$mutationName = $mutationValue;
        }
        $this->checkEntityAccess($source, $accessConditions, $entity);
        $propertyKeys = array_merge($fieldNames, array_keys($mutations));

        $source->update($entity, ['fields' => $propertyKeys], function () use ($unit, $entity) {
            $unit->onUpdate($entity);
        });

        return ['extra' => $unit->getUpdateExtraData($entity)];
    }

    /**
     * @throws EntityNotFoundException
     * @throws FilterNotAllowedException
     * @throws SortNotAllowedException
     * @throws ValidationException
     */
    private function readMethod(
        ReadMethodInterface $unit,
        SourceInterface $source,
        array $data = null,
        int $id = null
    ): array {
        if (null !== $id) {
            $response = $this->readOne($unit, $source, $id);
        } else {
            $response = $this->readList($unit, $source, $data);
        }

        return $response;
    }

    /** @throws EntityNotFoundException */
    private function readOne(ReadMethodInterface $unit, SourceInterface $source, int $id): array
    {
        $conditions = [
            new Condition\ExpressionCondition(Condition\ExpressionCondition::ACTION_EQUAL, 'id', $id),
            new Condition\ExpressionCondition(Condition\ExpressionCondition::ACTION_EQUAL, 'isDeleted', 0),
        ];
        $accessConditions = $unit->getAccessConditions(ActionInterface::READ);
        $conditions = array_merge($conditions, $unit->getReadOnePreConditions(), $accessConditions);
        $item = $source->findOne($unit->getEntityClass(), $conditions);
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
     * @throws ValidationException
     */
    private function readList(ReadMethodInterface $unit, SourceInterface $source, array $data): array
    {
        $conditions = [new Condition\ExpressionCondition(Condition\ExpressionCondition::ACTION_EQUAL, 'isDeleted', 0),];
        $accessConditions = $unit->getAccessConditions(ActionInterface::READ);
        $conditions = array_merge($conditions, $unit->getReadListPreConditions(), $accessConditions,
            $this->getUserConditions($unit, $data));
        $sort = $this->getSort($unit, $data);
        //@TODO validate 'page' and 'itemsPerPage'
        $page = (int)$data['page'];
        $page = $page > 0 ? $page : 1;
        $itemsPerPage = (int)$data['itemsPerPage'];
        if ($itemsPerPage > 50 || $itemsPerPage < 1) {
            $itemsPerPage = 10;
        }
        $list = $source->findList($unit->getEntityClass(), $conditions, $page, $itemsPerPage, $sort);
        $fields = $unit->getReadListFields();
        $views = $this->readViewCompiler->compileList($list->getItems(), $fields);

        $context = [
            'conditions' => $conditions,
        ];

        $response = [
            'items' => $views,
            'total' => $list->getTotal(),
            'extra' => $unit->getReadListExtraData($context),
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

    /**
     * @throws FilterNotAllowedException
     * @throws ValidationException
     */
    private function getUserConditions(ReadMethodInterface $unit, array $data)
    {
        $formData = [];
        foreach ($data as $key => $value) {
            if (preg_match('/f_(.+)/', $key, $matches)) {
                $formData[$matches[1]] = $value;
            }
        }

        $filterFormConfig = $unit->getFiltersFormConfig();
        if (null === $filterFormConfig) {
            if (count($formData) > 0) {
                throw new FilterNotAllowedException(array_key_first($formData));
            }

            return [];
        }
        $form = $this->formFactory->create($filterFormConfig);
        $form->submit($formData);
        $this->validateForm($form);

        $conditions = [];
        /** @var FormInterface $field */
        foreach ($form as $fieldName => $field) {
            $validItemValue = $field->getData();
            if (null === $validItemValue) {
                continue;
            }
            $itemConfig = $field->getConfig();
            $property = $itemConfig->getPropertyPath() ?? $fieldName;
            $itemType = $itemConfig->getType()->getInnerType();
            if ($itemType instanceof SearchType) {
                if (!empty($validItemValue)) {
                    $searchItemEntity = $itemConfig->getOptions()['entity'];
                    $sphinxClient = $this->container->get('ewll.sphinx.client');
                    $queryIds = $sphinxClient->find($searchItemEntity, $validItemValue);
                    if (count($queryIds) > 0) {
                        $conditions[] = new Condition\ExpressionCondition(
                            Condition\ExpressionCondition::ACTION_EQUAL,
                            'id',
                            $queryIds
                        );
                    } else {
                        $conditions[] = new Condition\ExpressionCondition(
                            Condition\ExpressionCondition::ACTION_EQUAL,
                            'id',
                            0
                        );
                    }
                }
            } elseif ($itemType instanceof IntegerType) {
                $conditions[] = new Condition\ExpressionCondition(
                    Condition\ExpressionCondition::ACTION_EQUAL,
                    $property,
                    $validItemValue
                );
            } elseif ($itemType instanceof ChoiceType) {
                $conditions[] = new Condition\ExpressionCondition(
                    Condition\ExpressionCondition::ACTION_EQUAL,
                    $property,
                    $validItemValue
                );
            } elseif ($itemType instanceof TextType) {
                $conditions[] = new Condition\ExpressionCondition(
                    Condition\ExpressionCondition::ACTION_EQUAL,
                    $property,
                    $validItemValue
                );
            } else {
                throw new RuntimeException('TODO'); // @TODO
            }
        }
        return $conditions;
    }

    /** @throws SortNotAllowedException */
    private function getSort(ReadMethodInterface $unit, array $data)
    {
        $sort = [];
        foreach ($data as $key => $value) {
            if (preg_match('/s_(.+)/', $key, $matches)) {
                if (!in_array($value, ['asc', 'desc'], true)) {
                    throw new RuntimeException("Filter value '$value', expect 'asc' or 'desc'");
                }
                $sort[] = [
//                    'type' => Repository::SORT_TYPE_SIMPLE,@TODO
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
        if ($methodName === ActionInterface::CONFIG) {
            return;
        } elseif ($action instanceof CustomAction) {
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
        $blockPrefixes = $field->vars['block_prefixes'];
        $type = $field->vars['block_prefixes'][\count($blockPrefixes) - 2];
        $definition = [
            'type' => $type,
            'constraints' => [],
            'disabled' => $field->vars['disabled'],
            'label' => $field->vars['label'],
        ];

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

    /** @throws AccessConditionException */
    private function checkEntityAccess(SourceInterface $source, array $accessConditions, object $entity): void
    {
        $isAccessGranted = false;
        foreach ($accessConditions as $accessCondition) {
            if ($accessCondition instanceof Condition\ExpressionCondition) {
                $accessConditionValue = $accessCondition->getValue();
                $isAccessConditionValueArray = is_array($accessConditionValue);
                $accessConditionField = $accessCondition->getField();
                switch ($accessCondition->getAction()) {
                    case Condition\ExpressionCondition::ACTION_EQUAL:
                        $isAccessGranted = $isAccessConditionValueArray
                            ? in_array($entity->$accessConditionField, $accessConditionValue, true)
                            : $entity->$accessConditionField === $accessConditionValue;
                        break;
                    case Condition\ExpressionCondition::ACTION_NOT_EQUAL:
                        $isAccessGranted = $isAccessConditionValueArray
                            ? !in_array($entity->$accessConditionField, $accessConditionValue, true)
                            : $entity->$accessConditionField !== $accessConditionValue;
                        break;
                    default:
                        throw new RuntimeException(
                            "Unknown ExpressionCondition action '{$accessCondition->getAction()}'"
                        );
                }
            } elseif ($accessCondition instanceof Condition\RelationCondition) {
                $isAccessGranted = $source->isEntityResolveRelationCondition($entity, $accessCondition);
            } else {
                throw new RuntimeException('Unknown AccessCondition');
            }
            if (!$isAccessGranted) {
                throw new AccessConditionException();
            }
        }
    }

    private function getSource(UnitInterface $unit): SourceInterface
    {
        $sourceClassName = $unit->getSourceClassName();
        foreach ($this->crudSources as $source) {
            if ($source instanceof $sourceClassName) {
                return $source;
            }
        }

        throw new RuntimeException("Source '$sourceClassName' not found");
    }
}
