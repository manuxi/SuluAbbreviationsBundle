<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Twig;

use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Tests\Unit\Traits\AbbreviationFactoryTrait;
use Manuxi\SuluAbbreviationsBundle\Twig\AbbreviationsTwigExtension;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AbbreviationsTwigExtensionTest extends TestCase
{
    use AbbreviationFactoryTrait;
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<AbbreviationRepository>
     */
    private ObjectProphecy $repository;

    private RequestStack $requestStack;

    private AbbreviationsTwigExtension $extension;

    protected function setUp(): void
    {
        $this->repository   = $this->prophesize(AbbreviationRepository::class);
        $this->requestStack = new RequestStack();
        $this->extension    = new AbbreviationsTwigExtension($this->repository->reveal(), $this->requestStack);
    }

    public function testFunctionNames(): void
    {
        $names = array_map(static fn ($function): string => $function->getName(), $this->extension->getFunctions());

        $this->assertSame(['sulu_resolve_abbreviation', 'sulu_get_abbreviations'], $names);
    }

    public function testGetAbbreviationsStartsAtFirstPageWithRequestLocale(): void
    {
        $this->pushRequest('de');
        $abbreviation = $this->createAbbreviation(1, 'de');

        $this->repository->findByFilters([], 1, 10, 10, 'de')->shouldBeCalledOnce()->willReturn([$abbreviation]);

        $this->assertSame([$abbreviation], $this->extension->getAbbreviations(10));
    }

    public function testGetAbbreviationsWithExplicitLocale(): void
    {
        $this->pushRequest('de');

        $this->repository->findByFilters([], 1, 100, 100, 'fr')->shouldBeCalledOnce()->willReturn([]);

        $this->assertSame([], $this->extension->getAbbreviations(100, 'fr'));
    }

    public function testGetAbbreviationsWithoutRequestFallsBackToDefaultLocale(): void
    {
        $this->repository->findByFilters([], 1, 100, 100, 'en')->shouldBeCalledOnce()->willReturn([]);

        $this->assertSame([], $this->extension->getAbbreviations());
    }

    public function testResolveReturnsPublishedAbbreviation(): void
    {
        $this->pushRequest('de');
        $abbreviation = $this->createAbbreviation(3, 'de');

        $this->repository->findById(3, 'de')->willReturn($abbreviation);

        $this->assertSame($abbreviation, $this->extension->resolveAbbreviation(3));
    }

    public function testResolveReturnsNullForUnpublishedOrMissingAbbreviation(): void
    {
        $this->repository->findById(3, 'en')->willReturn($this->createAbbreviation(3, 'en', false));
        $this->repository->findById(4, 'en')->willReturn(null);

        $this->assertNull($this->extension->resolveAbbreviation(3, 'en'));
        $this->assertNull($this->extension->resolveAbbreviation(4, 'en'));
    }

    private function pushRequest(string $locale): void
    {
        $request = new Request();
        $request->setLocale($locale);
        $this->requestStack->push($request);
    }
}
