<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Route;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Bundle\RouteBundle\Entity\RouteRepositoryInterface;

class AbbreviationRouteRemover
{
    public function __construct(
        private readonly RouteRepositoryInterface $routeRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Removes the routes (including the history) of an abbreviation, optionally limited to one locale.
     */
    public function remove(Abbreviation $abbreviation, ?string $locale = null): void
    {
        foreach ($this->routeRepository->findAllByEntity(Abbreviation::class, $abbreviation->getId(), $locale) as $route) {
            $this->entityManager->remove($route);
        }
    }
}
