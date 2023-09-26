<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Controller\Website;

use JMS\Serializer\SerializerBuilder;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\RouteBundle\Entity\RouteRepositoryInterface;
use Sulu\Bundle\WebsiteBundle\Resolver\TemplateAttributeResolverInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class AbbreviationsController extends AbstractController
{
    private TranslatorInterface $translator;
    private AbbreviationRepository $repository;
    private WebspaceManagerInterface $webspaceManager;
    private TemplateAttributeResolverInterface $templateAttributeResolver;
    private RouteRepositoryInterface $routeRepository;

    public function __construct(
        RequestStack $requestStack,
        MediaManagerInterface $mediaManager,
        AbbreviationRepository $repository,
        WebspaceManagerInterface $webspaceManager,
        TranslatorInterface $translator,
        TemplateAttributeResolverInterface $templateAttributeResolver,
        RouteRepositoryInterface $routeRepository
    ) {
        parent::__construct($requestStack, $mediaManager);

        $this->repository                = $repository;
        $this->webspaceManager           = $webspaceManager;
        $this->translator                = $translator;
        $this->templateAttributeResolver = $templateAttributeResolver;
        $this->routeRepository           = $routeRepository;
    }

    /**
     * @param Abbreviation $abbreviation
     * @param string $view
     * @param bool $preview
     * @param bool $partial
     * @return Response
     * @throws \Exception
     */
    public function indexAction(Abbreviation $abbreviation, string $view = 'pages/abbreviation', bool $preview = false, bool $partial = false): Response
    {
        $viewTemplate = $this->getViewTemplate($view, $this->request, $preview);

        $parameters = $this->templateAttributeResolver->resolve([
            'abbreviation'   => $abbreviation,
            'content' => [
                'title' => $this->translator->trans('sulu_abbreviations.abbreviations'),
                'name'  => $abbreviation->getName(),
            ],
/*            'path'          => $abbreviation->getRoutePath(),*/
            'extension'     => $this->extractExtension($abbreviation),
            'localizations' => $this->getLocalizationsArrayForEntity($abbreviation),
            'created'       => $abbreviation->getCreated(),
        ]);

        return $this->prepareResponse($viewTemplate, $parameters, $preview, $partial);
    }

    /**
     * With the help of this method the corresponding localisations for the
     * current abbreviations are found e.g. to be linked in the language switcher.
     * @param Abbreviation $abbreviation
     * @return array<string, array>
     */
    protected function getLocalizationsArrayForEntity(Abbreviation $abbreviation): array
    {
        $routes = $this->routeRepository->findAllByEntity(Abbreviation::class, (string)$abbreviation->getId());

        $localizations = [];
        foreach ($routes as $route) {
            $url = $this->webspaceManager->findUrlByResourceLocator(
                $route->getPath(),
                null,
                $route->getLocale()
            );

            $localizations[$route->getLocale()] = ['locale' => $route->getLocale(), 'url' => $url];
        }

        return $localizations;
    }

    private function extractExtension(Abbreviation $abbreviation): array
    {
        $serializer = SerializerBuilder::create()->build();
        return $serializer->toArray($abbreviation->getExt());
    }

    /**
     * @return string[]
     */
    public static function getSubscribedServices(): array
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                WebspaceManagerInterface::class,
                RouteRepositoryInterface::class,
                TemplateAttributeResolverInterface::class,
            ]
        );
    }

}
