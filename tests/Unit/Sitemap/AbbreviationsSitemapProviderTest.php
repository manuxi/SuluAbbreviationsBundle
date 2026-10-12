<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Sitemap;

use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Sitemap\AbbreviationsSitemapProvider;
use Manuxi\SuluAbbreviationsBundle\Tests\Unit\Traits\AbbreviationFactoryTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\WebsiteBundle\Sitemap\SitemapProviderInterface;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Portal;
use Sulu\Component\Webspace\PortalInformation;
use Sulu\Component\Webspace\Webspace;

class AbbreviationsSitemapProviderTest extends TestCase
{
    use AbbreviationFactoryTrait;
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<AbbreviationRepository>
     */
    private ObjectProphecy $repository;

    /**
     * @var ObjectProphecy<WebspaceManagerInterface>
     */
    private ObjectProphecy $webspaceManager;

    private AbbreviationsSitemapProvider $provider;

    private Webspace $webspace;

    private Portal $portal;

    protected function setUp(): void
    {
        $this->repository      = $this->prophesize(AbbreviationRepository::class);
        $this->webspaceManager = $this->prophesize(WebspaceManagerInterface::class);

        $this->webspace = new Webspace();
        $this->webspace->setKey('example');
        $this->webspace->addLocalization(new Localization('en'));
        $this->webspace->addLocalization(new Localization('de'));
        $this->webspace->setDefaultLocalization(new Localization('en'));

        $this->portal = new Portal();
        $this->portal->setKey('example');
        $this->portal->setDefaultLocalization(new Localization('de'));

        $this->provider = new AbbreviationsSitemapProvider(
            $this->repository->reveal(),
            $this->webspaceManager->reveal(),
            'prod'
        );
    }

    public function testAlias(): void
    {
        $this->assertSame('abbreviations', $this->provider->getAlias());
    }

    public function testBuildCreatesUrlsPerLocaleOfHost(): void
    {
        $this->givenPortalInformations('example.org', [
            $this->createPortalInformation(new Localization('en'), 'example.org/en'),
            $this->createPortalInformation(new Localization('de'), 'example.org/de'),
        ]);

        $english = $this->createAbbreviation(1, 'en', true, 'API');
        $english->setRoutePath('/abbreviations/api');
        $german = $this->createAbbreviation(2, 'de', true, 'API');
        $german->setRoutePath('/abkuerzungen/api');
        $german->setLocale('fr');

        $this->repository->findAllForSitemap('en', SitemapProviderInterface::PAGE_SIZE, 0)->willReturn([$english]);
        $this->repository->findAllForSitemap('de', SitemapProviderInterface::PAGE_SIZE, 0)->willReturn([$german]);

        $this->webspaceManager->findUrlByResourceLocator('/abbreviations/api', 'prod', 'en', 'example', 'example.org', 'https')
            ->willReturn('https://example.org/en/abbreviations/api');
        $this->webspaceManager->findUrlByResourceLocator('/abkuerzungen/api', 'prod', 'de', 'example', 'example.org', 'https')
            ->willReturn('https://example.org/de/abkuerzungen/api');

        $urls = $this->provider->build(1, 'https', 'example.org');

        $this->assertCount(2, $urls);
        $this->assertSame('https://example.org/en/abbreviations/api', $urls[0]->getLoc());
        $this->assertSame('en', $urls[0]->getLocale());
        $this->assertSame('en', $urls[0]->getDefaultLocale());
        $this->assertSame('https://example.org/de/abkuerzungen/api', $urls[1]->getLoc());
        $this->assertSame('de', $urls[1]->getLocale());
        $this->assertSame('de', $german->getLocale());
    }

    public function testBuildFallsBackToDefaultLocaleOfPortal(): void
    {
        $this->givenPortalInformations('example.org', [$this->createPortalInformation(null, 'example.org')]);

        $abbreviation = $this->createAbbreviation(1, 'de');
        $abbreviation->setRoutePath('/abkuerzungen/api');

        $this->repository->findAllForSitemap('de', SitemapProviderInterface::PAGE_SIZE, SitemapProviderInterface::PAGE_SIZE)
            ->willReturn([$abbreviation]);
        $this->webspaceManager->findUrlByResourceLocator('/abkuerzungen/api', 'prod', 'de', 'example', 'example.org', 'http')
            ->willReturn('http://example.org/abkuerzungen/api');

        $urls = $this->provider->build(2, 'http', 'example.org');

        $this->assertCount(1, $urls);
        $this->assertSame('de', $urls[0]->getLocale());
    }

    public function testBuildSkipsEntriesWithoutRouteOrUrl(): void
    {
        $this->givenPortalInformations('example.org', [$this->createPortalInformation(new Localization('en'), 'example.org')]);

        $withoutRoute = $this->createAbbreviation(1, 'en');
        $withoutUrl   = $this->createAbbreviation(2, 'en');
        $withoutUrl->setRoutePath('/abbreviations/unknown');

        $this->repository->findAllForSitemap('en', SitemapProviderInterface::PAGE_SIZE, 0)->willReturn([$withoutRoute, $withoutUrl]);
        $this->webspaceManager->findUrlByResourceLocator('/abbreviations/unknown', 'prod', 'en', 'example', 'example.org', 'https')
            ->willReturn(null);

        $this->assertSame([], $this->provider->build(1, 'https', 'example.org'));
    }

    public function testUnknownHostReturnsNoUrlsAndNoPages(): void
    {
        $this->givenPortalInformations('unknown.org', []);

        $this->assertSame([], $this->provider->build(1, 'https', 'unknown.org'));
        $this->assertSame(0, $this->provider->getMaxPage('https', 'unknown.org'));
    }

    public function testMaxPageUsesLargestLocale(): void
    {
        $this->givenPortalInformations('example.org', [
            $this->createPortalInformation(new Localization('en'), 'example.org/en'),
            $this->createPortalInformation(new Localization('de'), 'example.org/de'),
        ]);

        $this->repository->countForSitemap('en')->willReturn(SitemapProviderInterface::PAGE_SIZE + 1);
        $this->repository->countForSitemap('de')->willReturn(10);

        $this->assertSame(2, $this->provider->getMaxPage('https', 'example.org'));

        $sitemap = $this->provider->createSitemap('https', 'example.org');
        $this->assertSame('abbreviations', $sitemap->getAlias());
        $this->assertSame(2, $sitemap->getMaxPage());
    }

    /**
     * @param PortalInformation[] $portalInformations
     */
    private function givenPortalInformations(string $host, array $portalInformations): void
    {
        $this->webspaceManager->findPortalInformationsByHostIncludingSubdomains($host, 'prod')->willReturn($portalInformations);
    }

    private function createPortalInformation(?Localization $localization, string $url): PortalInformation
    {
        return new PortalInformation(
            RequestAnalyzerInterface::MATCH_TYPE_FULL,
            $this->webspace,
            $this->portal,
            $localization,
            $url
        );
    }
}
