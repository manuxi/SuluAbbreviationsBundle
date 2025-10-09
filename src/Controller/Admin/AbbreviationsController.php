<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Controller\Admin;

use Manuxi\SuluAbbreviationsBundle\Common\DoctrineListRepresentationFactory;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\Models\AbbreviationExcerptModel;
use Manuxi\SuluAbbreviationsBundle\Entity\Models\AbbreviationModel;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Models\AbbreviationSeoModel;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationPublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationSavedEvent;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationUnpublishedEvent;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteResource("abbreviation")
 */
class AbbreviationsController extends AbstractRestController implements ClassResourceInterface, SecuredControllerInterface
{
    use RequestParametersTrait;

    public function __construct(
        private readonly AbbreviationModel $abbreviationModel,
        private readonly AbbreviationSeoModel $abbreviationSeoModel,
        private readonly AbbreviationExcerptModel $abbreviationExcerptModel,
        private readonly DoctrineListRepresentationFactory $doctrineListRepresentationFactory,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly TrashManagerInterface $trashManager,
        private readonly EventDispatcherInterface $dispatcher,
        ViewHandlerInterface $viewHandler,
        ?TokenStorageInterface $tokenStorage = null
    ) {
        parent::__construct($viewHandler, $tokenStorage);
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
        $this->dispatcher->dispatch(new AbbreviationSavedEvent($entity));
        return $this->handleView($this->view($entity, 201));
    }

    /**
     * @Rest\Post("/abbreviations/{id}")
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
                    $this->dispatcher->dispatch(new AbbreviationPublishedEvent($entity));
                    break;
                case 'unpublish':
                    $entity = $this->abbreviationModel->unpublish($id, $request);
                    $this->dispatcher->dispatch(new AbbreviationUnpublishedEvent($entity));
                    break;
                case 'copy':
                    $entity = $this->abbreviationModel->copy($id, $request);
                    $this->dispatcher->dispatch(new AbbreviationSavedEvent($entity));
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
                    $this->dispatcher->dispatch(new AbbreviationSavedEvent($entity));
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

        $this->abbreviationSeoModel->updateAbbreviationSeo($entity->getSeo(), $request);
        $this->abbreviationExcerptModel->updateAbbreviationExcerpt($entity->getExcerpt(), $request);

        $this->dispatcher->dispatch(new AbbreviationSavedEvent($entity));

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

        $this->trashManager->store(Abbreviation::RESOURCE_KEY, $entity);

        $this->abbreviationModel->delete($entity);

        $this->dispatcher->dispatch(new AbbreviationRemovedEvent($entity));

        return $this->handleView($this->view(null, 204));
    }

    public function getSecurityContext(): string
    {
        return Abbreviation::SECURITY_CONTEXT;
    }

}
