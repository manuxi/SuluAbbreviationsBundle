<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Controller\Admin;

use Manuxi\SuluAbbreviationsBundle\Common\DoctrineListRepresentationFactory;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\Models\AbbreviationExcerptModel;
use Manuxi\SuluAbbreviationsBundle\Entity\Models\AbbreviationModel;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Models\AbbreviationSeoModel;
use Sulu\Bundle\RouteBundle\Entity\RouteRepositoryInterface;
use Sulu\Bundle\RouteBundle\Manager\RouteManagerInterface;
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;
use Sulu\Component\Rest\AbstractRestController;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\Exception\MissingParameterException;
use Sulu\Component\Rest\Exception\RestException;
use Sulu\Component\Rest\RequestParametersTrait;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @RouteResource("abbreviation")
 */
class AbbreviationsController extends AbstractRestController implements ClassResourceInterface, SecuredControllerInterface
{
    use RequestParametersTrait;

    private AbbreviationModel $abbreviationModel;
    private AbbreviationSeoModel $abbreviationSeoModel;
    private AbbreviationExcerptModel $abbreviationExcerptModel;
    private DoctrineListRepresentationFactory $doctrineListRepresentationFactory;
    private RouteManagerInterface $routeManager;
    private RouteRepositoryInterface $routeRepository;
    private SecurityCheckerInterface $securityChecker;
    private TrashManagerInterface $trashManager;

    public function __construct(
        AbbreviationModel $abbreviationModel,
        AbbreviationSeoModel $abbreviationSeoModel,
        AbbreviationExcerptModel $abbreviationExcerptModel,
        RouteManagerInterface $routeManager,
        RouteRepositoryInterface $routeRepository,
        DoctrineListRepresentationFactory $doctrineListRepresentationFactory,
        SecurityCheckerInterface $securityChecker,
        ViewHandlerInterface $viewHandler,
        TrashManagerInterface $trashManager,
        ?TokenStorageInterface $tokenStorage = null
    ) {
        parent::__construct($viewHandler, $tokenStorage);
        $this->abbreviationModel                 = $abbreviationModel;
        $this->abbreviationSeoModel              = $abbreviationSeoModel;
        $this->abbreviationExcerptModel          = $abbreviationExcerptModel;
        $this->doctrineListRepresentationFactory = $doctrineListRepresentationFactory;
        $this->routeManager                      = $routeManager;
        $this->routeRepository                   = $routeRepository;
        $this->securityChecker                   = $securityChecker;
        $this->trashManager = $trashManager;
    }

    public function cgetAction(Request $request): Response
    {
        $locale             = $request->query->get('locale');
        $listRepresentation = $this->doctrineListRepresentationFactory->createDoctrineListRepresentation(
            Abbreviation::RESOURCE_KEY,
            [],
            ['locale' => $locale]
        );

        return $this->handleView($this->view($listRepresentation));

    }

    /**
     * @param int $id
     * @param Request $request
     * @return Response
     * @throws EntityNotFoundException
     */
    public function getAction(int $id, Request $request): Response
    {
        $entity = $this->abbreviationModel->get($id, $request);
        return $this->handleView($this->view($entity));
    }

    /**
     * @param Request $request
     * @return Response
     * @throws EntityNotFoundException
     */
    public function postAction(Request $request): Response
    {
        $entity = $this->abbreviationModel->create($request);
        $this->updateRoutesForEntity($entity);

        return $this->handleView($this->view($entity, 201));
    }

    /**
     * @Rest\Post("/abbreviation/{id}")
     *
     * @param int $id
     * @param Request $request
     * @return Response
     * @throws MissingParameterException
     */
    public function postTriggerAction(int $id, Request $request): Response
    {
        $action = $this->getRequestParameter($request, 'action', true);

        try {
            switch ($action) {
                case 'publish':
                    $entity = $this->abbreviationModel->publish($id, $request);
                    break;
                case 'unpublish':
                    $entity = $this->abbreviationModel->unpublish($id, $request);
                    break;
                case 'copy':
                    $entity = $this->abbreviationModel->copy($id, $request);
                    break;
                case 'copy-locale':
                    $locale = $this->getRequestParameter($request, 'locale', true);
                    $srcLocale = $this->getRequestParameter($request, 'src', false, $locale);
                    $destLocales = $this->getRequestParameter($request, 'dest', true);
                    $destLocales = explode(',', $destLocales);

                    foreach ($destLocales as $destLocale) {
                        $this->securityChecker->checkPermission(
                            new SecurityCondition($this->getSecurityContext(), $destLocale),
                            PermissionTypes::EDIT
                        );
                    }

                    $entity = $this->abbreviationModel->copyLanguage($id, $request, $srcLocale, $destLocales);
                    break;
                default:
                    throw new BadRequestHttpException(sprintf('Unknown action "%s".', $action));
            }
        } catch (RestException $exc) {
            $view = $this->view($exc->toArray(), 400);
            return $this->handleView($view);
        }

        return $this->handleView($this->view($entity));
    }

    /**
     * @param int $id
     * @param Request $request
     * @return Response
     * @throws EntityNotFoundException
     */
    public function putAction(int $id, Request $request): Response
    {
        $entity = $this->abbreviationModel->update($id, $request);
        $this->updateRoutesForEntity($entity);

        $this->abbreviationSeoModel->updateAbbreviationSeo($entity->getSeo(), $request);
        $this->abbreviationExcerptModel->updateAbbreviationExcerpt($entity->setExcerpt(), $request);

        return $this->handleView($this->view($entity));
    }

    /**
     * @param int $id
     * @param Request $request
     * @return Response
     * @throws EntityNotFoundException
     */
    public function deleteAction(int $id, Request $request): Response
    {
        $entity = $this->abbreviationModel->get($id, $request);
        $name = $entity->getName();

        $this->trashManager->store(Abbreviation::RESOURCE_KEY, $entity);

        $this->removeRoutesForEntity($entity);

        $this->abbreviationModel->delete($id, $name);
        return $this->handleView($this->view(null, 204));
    }

    public function getSecurityContext(): string
    {
        return Abbreviation::SECURITY_CONTEXT;
    }

    protected function updateRoutesForEntity(Abbreviation $entity): void
    {
        $this->routeManager->createOrUpdateByAttributes(
            Abbreviation::class,
            (string) $entity->getId(),
            $entity->getLocale(),
            $entity->getRoutePath()
        );
    }

    protected function removeRoutesForEntity(Abbreviation $entity): void
    {
        $routes = $this->routeRepository->findAllByEntity(
            Abbreviation::class,
            (string) $entity->getId(),
            $entity->getLocale()
        );

        foreach ($routes as $route) {
            $this->routeRepository->remove($route);
        }
    }
}
