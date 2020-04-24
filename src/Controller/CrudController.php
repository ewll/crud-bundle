<?php namespace Ewll\CrudBundle\Controller;

use Ewll\CrudBundle\Action\ActionInterface;
use Ewll\CrudBundle\Crud;
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
use Ewll\CrudBundle\Action\CrudAction;
use Ewll\CrudBundle\Action\CustomAction;
use Ewll\CrudBundle\UserProvider\AuthenticatorUserProvider;
use Ewll\CrudBundle\UserProvider\Exception\NoUserException;
use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CrudController extends AbstractController
{
    const ROUTE_NAME_CONFIG = 'crud.config';
    const ROUTE_NAME_CREATE = 'crud.create';
    const ROUTE_NAME_UPDATE = 'crud.update';
    const ROUTE_NAME_CUSTOM_ACTION = 'crud.custom-action';
    const ROUTE_NAME_CUSTOM_ACTION_TARGET = 'crud.custom-action-target';
    const ROUTE_NAME_CUSTOM_ACTION_FORM = 'crud.custom-action-form';
    const ROUTE_NAME_DELETE = 'crud.delete';
    const ROUTE_NAME_READ = 'crud.read';
    const ROUTE_NAME_READ_LIST = 'crud.read-list';
    const ROUTE_NAME_FORM_CREATE = 'crud.form-create';
    const ROUTE_NAME_FORM_UPDATE = 'crud.form-update';

    private $crud;
    private $repositoryProvider;
    private $authenticatorUserProvider;

    public function __construct(
        Crud $crud,
        RepositoryProvider $repositoryProvider,
        AuthenticatorUserProvider $authenticatorUserProvider
    ) {
        $this->crud = $crud;
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticatorUserProvider = $authenticatorUserProvider;
    }

    public function action(Request $request, string $unitName, int $id = null, string $customActionName = null)
    {
        try {
            $data = null;
            $userProvider = $this->authenticatorUserProvider;
            switch ($request->attributes->get('_route')) {
                case self::ROUTE_NAME_CONFIG:
                    $action = new CrudAction($userProvider, ActionInterface::CONFIG, $unitName);
                    break;
                case self::ROUTE_NAME_CREATE:
                    $action = new CrudAction(
                        $userProvider,
                        ActionInterface::CREATE,
                        $unitName,
                        null,
                        $request->request->all()
                    );
                    break;
                case self::ROUTE_NAME_UPDATE:
                    $action = new CrudAction(
                        $userProvider,
                        ActionInterface::UPDATE,
                        $unitName,
                        $id,
                        $request->request->all()
                    );
                    break;
                case self::ROUTE_NAME_CUSTOM_ACTION:
                    $action = new CustomAction(
                        $userProvider,
                        ActionInterface::CUSTOM,
                        $unitName,
                        $request->request->all(),
                        $customActionName
                    );
                    break;
                case self::ROUTE_NAME_CUSTOM_ACTION_TARGET:
                    $action = new CustomAction(
                        $userProvider,
                        ActionInterface::CUSTOM,
                        $unitName,
                        $request->request->all(),
                        $customActionName,
                        $id
                    );
                    break;
                case self::ROUTE_NAME_CUSTOM_ACTION_FORM:
                    $action = new CustomAction(
                        $userProvider,
                        ActionInterface::FORM_CUSTOM,
                        $unitName,
                        $request->query->all(),
                        $customActionName,
                        $id
                    );
                    break;
                case self::ROUTE_NAME_DELETE:
                    $action = new CrudAction(
                        $userProvider,
                        ActionInterface::DELETE,
                        $unitName,
                        $id,
                        $request->request->all()
                    );
                    break;
                case self::ROUTE_NAME_READ:
                    $action = new CrudAction($userProvider, ActionInterface::READ, $unitName, $id);
                    break;
                case self::ROUTE_NAME_READ_LIST:
                    $action = new CrudAction(
                        $userProvider,
                        ActionInterface::READ,
                        $unitName,
                        null,
                        $request->query->all()
                    );
                    break;
                case self::ROUTE_NAME_FORM_CREATE:
                    $action = new CrudAction($userProvider, ActionInterface::FORM_CREATE, $unitName, $id);
                    break;
                case self::ROUTE_NAME_FORM_UPDATE:
                    $action = new CrudAction($userProvider, ActionInterface::FORM_UPDATE, $unitName, $id);
                    break;
                default:
                    throw new RuntimeException('Unhandled route');
            }
            $response = $this->crud->handle($action);

            return new JsonResponse($response);
        } catch (
        UnitNotExistsException
        |UnitMethodNotAllowedException
        |FilterNotAllowedException
        |SortNotAllowedException
        |PropertyNotExistsException
        |PropertyNotAllowedException
        |AccessConditionException
        $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_ACCEPTABLE);
        } catch (EntityNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (NoUserException|CsrfException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        } catch (AccessNotGrantedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_METHOD_NOT_ALLOWED);
        } catch (ValidationException $e) {
            return new JsonResponse(['errors' => $e->getErrors()], Response::HTTP_BAD_REQUEST);
        }
    }
}
