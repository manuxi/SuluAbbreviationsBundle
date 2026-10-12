<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Sitemap;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Sulu\Bundle\WebsiteBundle\Sitemap\Sitemap;
use Sulu\Bundle\WebsiteBundle\Sitemap\SitemapProviderInterface;
use Sulu\Bundle\WebsiteBundle\Sitemap\SitemapUrl;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Portal;
use Sulu\Component\Webspace\PortalInformation;
use Sulu\Component\Webspace\Webspace;

class AbbreviationsSitemapProvider implements SitemapProviderInterface
{
    public function __construct(
        private readonly AbbreviationRepository $repository,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly string $environment,
    ) {
    }

    /**
     * @return SitemapUrl[]
     */
    public function build($page, $scheme, $host): array
    {
        $result = [];
        foreach ($this->getPortalInformationsByLocale($host) as $locale => $portalInformation) {
            $abbreviations = $this->repository->findAllForSitemap(
                $locale,
                self::PAGE_SIZE,
                ((int) $page - 1) * self::PAGE_SIZE
            );

            foreach ($abbreviations as $abbreviation) {
                $sitemapUrl = $this->createSitemapUrl($abbreviation, $locale, $portalInformation, $scheme, $host);
                if (null !== $sitemapUrl) {
                    $result[] = $sitemapUrl;
                }
            }
        }

        return $result;
    }

    public function createSitemap($scheme, $host): Sitemap
    {
        return new Sitemap($this->getAlias(), $this->getMaxPage($scheme, $host));
    }

    public function getAlias(): string
    {
        return 'abbreviations';
    }

    public function getMaxPage($scheme, $host): int
    {
        $maxPage = 0;
        foreach (array_keys($this->getPortalInformationsByLocale($host)) as $locale) {
            $maxPage = max($maxPage, (int) ceil($this->repository->countForSitemap($locale) / self::PAGE_SIZE));
        }

        return $maxPage;
    }

    /**
     * Returns one portal information per locale available for the given host. Portal informations without
     * localization (e.g. a host without locale prefix) fall back to the default locale of the portal.
     *
     * @return array<string, PortalInformation>
     */
    private function getPortalInformationsByLocale(string $host): array
    {
        $result = [];
        foreach ($this->webspaceManager->findPortalInformationsByHostIncludingSubdomains($host, $this->environment) as $portalInformation) {
            /** @var Localization|null $localization */
            $localization = $portalInformation->getLocalization();
            if (null === $localization) {
                /** @var Portal|null $portal */
                $portal = $portalInformation->getPortal();
                /** @var Localization|null $localization */
                $localization = $portal?->getDefaultLocalization();
            }

            if (null === $localization) {
                continue;
            }

            $result[$localization->getLocale()] ??= $portalInformation;
        }

        return $result;
    }

    private function createSitemapUrl(
        Abbreviation $abbreviation,
        string $locale,
        PortalInformation $portalInformation,
        string $scheme,
        string $host,
    ): ?SitemapUrl {
        $abbreviation->setLocale($locale);

        $routePath = $abbreviation->getRoutePath();
        if (null === $routePath || '' === $routePath) {
            return null;
        }

        $url = $this->webspaceManager->findUrlByResourceLocator(
            $routePath,
            $this->environment,
            $locale,
            $portalInformation->getWebspaceKey(),
            $host,
            $scheme
        );

        if (null === $url) {
            return null;
        }

        /** @var Webspace|null $webspace */
        $webspace = $portalInformation->getWebspace();

        return new SitemapUrl(
            $url,
            $locale,
            $webspace?->getDefaultLocalization()->getLocale(Localization::DASH) ?? $locale,
            $abbreviation->getChanged()
        );
    }
}
