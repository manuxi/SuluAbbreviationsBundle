<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Twig;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AbbreviationsTwigExtension extends AbstractExtension
{
    private const DEFAULT_LOCALE = 'en';

    public function __construct(
        private readonly AbbreviationRepository $abbreviationRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sulu_resolve_abbreviation', [$this, 'resolveAbbreviation']),
            new TwigFunction('sulu_get_abbreviations', [$this, 'getAbbreviations']),
        ];
    }

    /**
     * Returns the abbreviation if it is published in the given locale (default: locale of the current request).
     */
    public function resolveAbbreviation(int $id, ?string $locale = null): ?Abbreviation
    {
        $abbreviation = $this->abbreviationRepository->findById($id, $this->resolveLocale($locale));

        return true === $abbreviation?->isPublished() ? $abbreviation : null;
    }

    /**
     * Returns the published abbreviations of the given locale (default: locale of the current request).
     *
     * @return Abbreviation[]
     */
    public function getAbbreviations(int $limit = 100, ?string $locale = null): array
    {
        return $this->abbreviationRepository->findByFilters([], 1, $limit, $limit, $this->resolveLocale($locale));
    }

    private function resolveLocale(?string $locale): string
    {
        if (null !== $locale && '' !== $locale) {
            return $locale;
        }

        return $this->requestStack->getCurrentRequest()?->getLocale() ?? self::DEFAULT_LOCALE;
    }
}
