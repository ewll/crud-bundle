<?php namespace Ewll\CrudBundle;

use Ewll\CrudBundle\Exception\AccessConditionException;
use Ewll\CrudBundle\Exception\AccessNotGrantedException;
use Ewll\CrudBundle\Exception\EntityNotFoundException;
use Ewll\CrudBundle\Exception\FilterNotAllowedException;
use Ewll\CrudBundle\Exception\PropertyNotAllowedException;
use Ewll\CrudBundle\Exception\PropertyNotExistsException;
use Ewll\CrudBundle\Exception\SortNotAllowedException;
use Ewll\CrudBundle\Exception\UnitMethodNotAllowedException;
use Ewll\CrudBundle\Exception\UnitNotExistsException;
use Ewll\CrudBundle\Exception\UserNotAuthorizedException;
use Ewll\CrudBundle\Exception\ValidationException;
use Ewll\CrudBundle\Preformation\Preformator;
use Ewll\CrudBundle\ReadViewCompiler\ReadViewCompiler;
use Ewll\CrudBundle\Unit\CreateMethodInterface;
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
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Crud
{
    const METHOD_READ = 'read';
    const METHOD_CREATE = 'create';
    const METHOD_UPDATE = 'update';
    const METHOD_DELETE = 'delete';
    const METHOD_FORM_CREATE = 'formCreate';
    const METHOD_FORM_UPDATE = 'formUpdate';

    const CONSTRAINT_NAME_ENTITY = 'globalEntity';

    private $validator;
    private $repositoryProvider;
    private $accessRuleProvider;
    private $accessChecker;
    private $authenticator;
    private $readViewCompiler;
    private $preformator;
    private $formFactory;
    /** @var UnitInterface[] */
    private $crudUnits;

    public function __construct(
        ValidatorInterface $validator,
        RepositoryProvider $repositoryProvider,
        AccessRuleProvider $accessRuleProvider,
        AccessChecker $accessChecker,
        Authenticator $authenticator,
        ReadViewCompiler $readViewCompiler,
        Preformator $preformator,
        FormFactoryInterface $formFactory,
        iterable $crudUnits
    ) {
        $this->validator = $validator;
        $this->repositoryProvider = $repositoryProvider;
        $this->accessRuleProvider = $accessRuleProvider;
        $this->accessChecker = $accessChecker;
        $this->authenticator = $authenticator;
        $this->readViewCompiler = $readViewCompiler;
        $this->preformator = $preformator;
        $this->formFactory = $formFactory;
        $this->crudUnits = $crudUnits;
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
     * @throws UserNotAuthorizedException
     * @throws AccessConditionException
     */
    public function handle(string $unitName, string $method, array $data = null, int $id = null): array
    {
        $unit = $this->getUnit($unitName);
        $accessRuleClassName = $unit->getAccessRuleClassName();
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
        if (!$this->isMethodAllowed($unit, $method)) {
            throw new UnitMethodNotAllowedException($method);
        }
        $entityClass = $unit->getEntityClass();
        $repository = $this->repositoryProvider->get($entityClass);
        $function = "{$method}Method";
        $response = $this->$function($unit, $repository, $data, $id);

        return $response;
    }

    private function formCreateMethod(
        CreateMethodInterface $unit,
        Repository $repository
    ): array {
        $formBuilder = $this->createFormBuilder();
        $hasPreformation = $unit->hasPreformation();
        if ($hasPreformation) {
            $this->preformator->fillPreformBuilder($unit, $formBuilder);
        } else {
            $unit->fillCreateFormBuilder($formBuilder);
        }
        $fieldNames = $this->getFieldNamesFromFormBuilder($formBuilder);
        $form = [];
        foreach ($fieldNames as $fieldName) {
            $form[$fieldName] = [];
        }

        return $form;
    }

    /** @throws EntityNotFoundException */
    private function formUpdateMethod(
        UpdateMethodInterface $unit,
        Repository $repository,
        array $data = null,
        int $id
    ): array {
        $item = $this->getEntityById($unit, $repository, $id);
        $formBuilder = $this->createFormBuilder();
        $hasPreformation = $unit->hasPreformation();
        if ($hasPreformation) {
            $preformData = (array)$item;
            $this->preformator->fillPreformBuilder($unit, $formBuilder, $preformData);
        } else {
            $unit->fillUpdateFormBuilder($formBuilder);
        }
        $fieldNames = $this->getFieldNamesFromFormBuilder($formBuilder);
        $form = [];
        foreach ($fieldNames as $fieldName) {
            $form[$fieldName] = [];
        }

        if ($hasPreformation) {
            $parameters = $this->preformator->reverse($unit, $item);
        } else {
            $parameters = [];
            foreach ($form as $fieldName => $field) {
                $parameters[$fieldName] = $item->$fieldName;
            }
        }
        foreach ($form as $fieldName => &$field) {
            $field['value'] = $parameters[$fieldName];
        }

        return $form;
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
        if ($unit->hasPreformation()) {
            $properties = $this->preformator->preformate($unit, $properties);
        }
        $formBuilder = $this->createFormBuilder();
        $unit->fillCreateFormBuilder($formBuilder);
        $form = $formBuilder->getForm();
        $form->submit($properties);
        $this->validateForm($form);
        $data = $form->getData();

        $fieldNames = $this->getFieldNamesFromFormBuilder($formBuilder);
        $entityClass = $unit->getEntityClass();
        $entity = new $entityClass();
        $this->fillEntity($entity, $entityClass, $fieldNames, $data);

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
        if ($unit->hasPreformation()) {
            $properties = $this->preformator->preformate($unit, $properties);
        }
        $formBuilder = $this->createFormBuilder();
        $unit->fillUpdateFormBuilder($formBuilder);
        $form = $formBuilder->getForm();
        $form->submit($properties);
        $this->validateForm($form);
        $data = $form->getData();

        $entityClass = $unit->getEntityClass();
        $item = $this->getEntityById($unit, $repository, $id);
        $fieldNames = $this->getFieldNamesFromFormBuilder($formBuilder);
        $this->fillEntity($item, $entityClass, $fieldNames, $data);
        $propertyKeys = array_keys($data);
        $repository->update($item, $propertyKeys);

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
        $filters = array_merge($filters, $unit->getAccessConditions());
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
        $filters = array_merge($filters, $this->getFilters($unit, $data), $unit->getAccessConditions());
        $sort = $this->getSort($unit, $data);
        //@TODO validate 'page' and 'itemsPerPage'
        $page = (int)$data['page'];
        $page = $page > 0 ? $page : 1;
        $itemsPerPage = (int)$data['itemsPerPage'];
        if ($itemsPerPage > 50 || $itemsPerPage < 1) {
            $itemsPerPage = 10;
        }
        $items = $repository->findBy($filters, null, $page, $itemsPerPage, $sort);
        $total = $repository->getFoundRows();
        $fields = $unit->getReadManyFields();
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
    private function fillEntity($entity, string $entityClass, array $allowedProperties, array $properties)
    {
        foreach ($properties as $propertyName => $propertyValue) {
            if (!property_exists($entityClass, $propertyName)) {
                throw new PropertyNotExistsException($propertyName);
            }
            if (!in_array($propertyName, $allowedProperties, true)) {
                throw new PropertyNotAllowedException($propertyName);
            }
            $entity->$propertyName = $propertyValue;
        }
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
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[$error->getOrigin()->getName()] = $error->getMessage();
            }
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

        $filters = $unit->prepareFilters($filters);

        return $filters;
    }

    /** @throws SortNotAllowedException */
    private function getSort(ReadMethodInterface $unit, array $data)
    {
        $sort = [];
        foreach ($data as $key => $value) {
            if (preg_match('/s_(asc|desc)_(.+)/', $key, $matches)) {
                $sort[] = [
                    'field' => $matches[2],
                    'value' => $value,
                    'method' => $matches[1],
                ];
            }
        }
        foreach ($sort as $item) {
            if (!in_array($item['field'], $unit->getAllowedSortFields(), true)) {
                throw new SortNotAllowedException($item['field']);
            }
        }

        return $sort;
    }

    private function isMethodAllowed(UnitInterface $unit, string $method): bool
    {
        $methodInterfaceRelation = [
            Crud::METHOD_READ => ReadMethodInterface::class,
            Crud::METHOD_CREATE => CreateMethodInterface::class,
            Crud::METHOD_UPDATE => UpdateMethodInterface::class,
            Crud::METHOD_DELETE => DeleteMethodInterface::class,
            Crud::METHOD_FORM_CREATE => CreateMethodInterface::class,
            Crud::METHOD_FORM_UPDATE => UpdateMethodInterface::class,
        ];
        $isMethodAllowed = $unit instanceof $methodInterfaceRelation[$method];

        return $isMethodAllowed;
    }

    private function createFormBuilder(): FormBuilderInterface
    {
        return $this->formFactory->createBuilder(FormType::class, null, ['csrf_protection' => false]);
    }

    private function getFieldNamesFromFormBuilder(FormBuilderInterface $formBuilder): array
    {
        $fieldNames = [];
        /** @var FormInterface[] $fields */
        $fields = $formBuilder->all();
        foreach ($fields as $field) {
            $fieldNames[] = $field->getName();
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
}
