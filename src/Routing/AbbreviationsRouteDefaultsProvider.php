<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Routing;

use Manuxi\SuluAbbreviationsBundle\Controller\Website\AbbreviationsController;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Sulu\Bundle\RouteBundle\Routing\Defaults\RouteDefaultsProviderInterface;

class AbbreviationsRouteDefaultsProvider implements RouteDefaultsProviderInterface
{

    private AbbreviationRepository $repository;

    public function __construct(AbbreviationRepository $repository) {
        $this->repository = $repository;
    }

    /**
     * @param $entityClass
     * @param $id
     * @param $locale
     * @param null $object
     * @return mixed[]
     */
    public function getByEntity($entityClass, $id, $locale, $object = null)
    {
        return [
            '_controller' => AbbreviationsController::class . '::indexAction',
            'abbreviation' => $this->repository->findById((int)$id, $locale),
        ];
    }

    public function isPublished($entityClass, $id, $locale): bool
    {
        $abbreviation = $this->repository->findById((int)$id, $locale);
        if (!$this->supports($entityClass) || !$abbreviation instanceof Abbreviation) {
            return false;
        }
        return $abbreviation->isPublished();
    }

    public function supports($entityClass)
    {
        return Abbreviation::class === $entityClass;
    }
}
