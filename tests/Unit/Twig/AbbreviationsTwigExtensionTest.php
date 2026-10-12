<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Twig;

use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Resolver\AbbreviationResolver;
use Manuxi\SuluAbbreviationsBundle\Twig\AbbreviationsTwigExtension;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AbbreviationsTwigExtensionTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AbbreviationRepository> */
    private ObjectProphecy $repository;

    /** @var ObjectProphecy<AbbreviationResolver> */
    private ObjectProphecy $resolver;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->repository   = $this->prophesize(AbbreviationRepository::class);
        $this->resolver     = $this->prophesize(AbbreviationResolver::class);
        $this->requestStack = new RequestStack();
    }

    private function createExtension(bool $autoAbbr = false): AbbreviationsTwigExtension
    {
        return new AbbreviationsTwigExtension(
            $this->repository->reveal(),
            $this->resolver->reveal(),
            $this->requestStack,
            $autoAbbr
        );
    }

    public function testRegistersFunctions(): void
    {
        $names = array_map(static fn ($function): string => $function->getName(), $this->createExtension()->getFunctions());

        $this->assertSame(['sulu_resolve_abbreviation', 'sulu_get_abbreviations'], $names);
    }

    public function testAbbrFilterIsOnlyRegisteredWhenEnabled(): void
    {
        $this->assertSame([], $this->createExtension()->getFilters());

        $filters = $this->createExtension(true)->getFilters();
        $this->assertCount(1, $filters);
        $this->assertSame('abbr', $filters[0]->getName());
    }

    public function testResolveAbbreviationUsesGivenLocale(): void
    {
        $this->resolver->resolve('a', 'de')->willReturn(['name' => 'A']);

        $this->assertSame(['name' => 'A'], $this->createExtension()->resolveAbbreviation('a', 'de'));
    }

    public function testResolveAbbreviationUsesRequestLocale(): void
    {
        $request = new Request();
        $request->setLocale('fr');
        $this->requestStack->push($request);

        $this->resolver->resolve('a', 'fr')->willReturn(null);

        $this->assertNull($this->createExtension()->resolveAbbreviation('a'));
    }

    public function testResolveAbbreviationFallsBackToDefaultLocale(): void
    {
        $this->resolver->resolve('a', 'en')->willReturn(['name' => 'A']);

        $this->assertSame(['name' => 'A'], $this->createExtension()->resolveAbbreviation('a'));
    }

    public function testGetAbbreviationsLoadsPublishedIdsInLocale(): void
    {
        $this->repository->findPublishedIds('de', 5)->willReturn(['a', 'b']);
        $this->resolver->resolveMany(['a', 'b'], 'de')->willReturn([['name' => 'A'], ['name' => 'B']]);

        $this->assertSame([['name' => 'A'], ['name' => 'B']], $this->createExtension()->getAbbreviations(5, 'de'));
    }

    public function testGetAbbreviationsUsesPublishedOnlyWithDefaultLimit(): void
    {
        $this->repository->findPublishedIds('en', 100)->willReturn([]);
        $this->resolver->resolveMany([], 'en')->willReturn([]);

        $this->assertSame([], $this->createExtension()->getAbbreviations());
    }

    private function prepareEntries(): void
    {
        $this->repository->findPublishedIds('en', 1000)->willReturn(['a', 'b']);
        $this->resolver->resolveMany(['a', 'b'], 'en')->willReturn([
            ['name' => 'HTML', 'explanation' => 'HyperText "Markup" Language'],
            ['name' => 'HTML5', 'explanation' => 'Fifth version'],
            ['name' => 'Empty', 'explanation' => ''],
        ]);
    }

    public function testWrapAbbreviationsWrapsKnownNamesInTextNodes(): void
    {
        $this->prepareEntries();

        $html = $this->createExtension(true)->wrapAbbreviations('<p>HTML is great, HTML5 too.</p>');

        $this->assertSame(
            '<p><abbr title="HyperText &quot;Markup&quot; Language">HTML</abbr> is great, <abbr title="Fifth version">HTML5</abbr> too.</p>',
            $html
        );
    }

    public function testWrapAbbreviationsSkipsExistingLinksAndAbbrTags(): void
    {
        $this->prepareEntries();

        $source = '<a href="/x">HTML</a> <abbr title="x">HTML</abbr> <code>HTML</code>';

        $this->assertSame($source, $this->createExtension(true)->wrapAbbreviations($source));
    }

    public function testWrapAbbreviationsRespectsWordBoundaries(): void
    {
        $this->prepareEntries();

        $source = '<p>XHTML and HTMLs</p>';

        $this->assertSame($source, $this->createExtension(true)->wrapAbbreviations($source));
    }

    public function testWrapAbbreviationsHandlesEmptyInput(): void
    {
        $extension = $this->createExtension(true);

        $this->assertSame('', $extension->wrapAbbreviations(null));
        $this->assertSame('', $extension->wrapAbbreviations(''));
    }

    public function testWrapAbbreviationsReturnsInputWithoutPublishedAbbreviations(): void
    {
        $this->repository->findPublishedIds('en', 1000)->willReturn([]);
        $this->resolver->resolveMany([], 'en')->willReturn([]);

        $this->assertSame('<p>HTML</p>', $this->createExtension(true)->wrapAbbreviations('<p>HTML</p>'));
    }

    public function testWrapAbbreviationsLoadsEntriesOncePerLocale(): void
    {
        $this->repository->findPublishedIds('en', 1000)->willReturn(['a'])->shouldBeCalledTimes(1);
        $this->resolver->resolveMany(['a'], 'en')->willReturn([['name' => 'CSS', 'explanation' => 'Styles']])->shouldBeCalledTimes(1);

        $extension = $this->createExtension(true);
        $extension->wrapAbbreviations('CSS');
        $extension->wrapAbbreviations('CSS again');
    }
}
