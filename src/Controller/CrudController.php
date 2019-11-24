<?php namespace Ewll\CrudBundle\Controller;

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
use Ewll\CrudBundle\Exception\UserNotAuthorizedException;
use Ewll\CrudBundle\Exception\ValidationException;
use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CrudController extends AbstractController
{
    const ROUTE_NAME_CREATE = 'crud.create';
    const ROUTE_NAME_UPDATE = 'crud.update';
    const ROUTE_NAME_DELETE = 'crud.delete';
    const ROUTE_NAME_READ = 'crud.read';
    const ROUTE_NAME_READ_LIST = 'crud.read-list';
    const ROUTE_NAME_FORM_CREATE = 'crud.form-create';
    const ROUTE_NAME_FORM_UPDATE = 'crud.form-update';

    private $crud;
    private $repositoryProvider;

    public function __construct(Crud $crud, RepositoryProvider $repositoryProvider)
    {
        $this->crud = $crud;
        $this->repositoryProvider = $repositoryProvider;
    }

    public function action(Request $request, string $unitName, int $id = null)
    {
        try {
            $data = null;
            switch ($request->attributes->get('_route')) {
                case self::ROUTE_NAME_CREATE:
                    $method = Crud::METHOD_CREATE;
                    $data = $request->request->all();
                    break;
                case self::ROUTE_NAME_UPDATE:
                    $method = Crud::METHOD_UPDATE;
                    $data = $request->request->all();
                    break;
                case self::ROUTE_NAME_DELETE:
                    $method = Crud::METHOD_DELETE;
                    $data = $request->request->all();
                    break;
                case self::ROUTE_NAME_READ:
                    $method = Crud::METHOD_READ;
                    break;
                case self::ROUTE_NAME_READ_LIST:
                    $method = Crud::METHOD_READ;
                    $data = $request->query->all();
                    break;
                case self::ROUTE_NAME_FORM_CREATE:
                    $method = Crud::METHOD_FORM_CREATE;
                    break;
                case self::ROUTE_NAME_FORM_UPDATE:
                    $method = Crud::METHOD_FORM_UPDATE;
                    break;
                default:
                    throw new RuntimeException('Unhandled route');
            }
            $response = $this->crud->handle($unitName, $method, $data, $id);

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
        } catch (UserNotAuthorizedException|CsrfException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        } catch (AccessNotGrantedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_METHOD_NOT_ALLOWED);
        } catch (ValidationException $e) {
            return new JsonResponse(['errors' => $e->getErrors()], Response::HTTP_BAD_REQUEST);
        }
    }
}
