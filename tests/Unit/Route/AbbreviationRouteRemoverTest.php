<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Route;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Route\AbbreviationRouteRemover;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\RouteBundle\Entity\Route;
use Sulu\Bundle\RouteBundle\Entity\RouteRepositoryInterface;

class AbbreviationRouteRemoverTest extends TestCase
{
    use ProphecyTrait;

    public function testRemovesAllRoutesOfTheAbbreviation(): void
    {
        $abbreviation = new Abbreviation();
        $first        = new Route();
        $second       = new Route();
        $first->setPath('/first');
        $second->setPath('/second');

        $repository = $this->prophesize(RouteRepositoryInterface::class);
        $repository->findAllByEntity(Abbreviation::class, $abbreviation->getId(), null)->willReturn([$first, $second]);
        $entityManager = $this->prophesize(EntityManagerInterface::class);

        (new AbbreviationRouteRemover($repository->reveal(), $entityManager->reveal()))->remove($abbreviation);

        $entityManager->remove($first)->shouldHaveBeenCalledOnce();
        $entityManager->remove($second)->shouldHaveBeenCalledOnce();
    }

    public function testRemovesOnlyRoutesOfTheGivenLocale(): void
    {
        $abbreviation = new Abbreviation();
        $route        = new Route();

        $repository = $this->prophesize(RouteRepositoryInterface::class);
        $repository->findAllByEntity(Abbreviation::class, $abbreviation->getId(), 'de')->willReturn([$route]);
        $entityManager = $this->prophesize(EntityManagerInterface::class);

        (new AbbreviationRouteRemover($repository->reveal(), $entityManager->reveal()))->remove($abbreviation, 'de');

        $entityManager->remove($route)->shouldHaveBeenCalledOnce();
    }

    public function testDoesNothingWithoutRoutes(): void
    {
        $abbreviation = new Abbreviation();

        $repository = $this->prophesize(RouteRepositoryInterface::class);
        $repository->findAllByEntity(Abbreviation::class, $abbreviation->getId(), null)->willReturn([]);
        $entityManager = $this->prophesize(EntityManagerInterface::class);

        (new AbbreviationRouteRemover($repository->reveal(), $entityManager->reveal()))->remove($abbreviation);

        $entityManager->remove(Argument::any())->shouldNotHaveBeenCalled();
    }
}
