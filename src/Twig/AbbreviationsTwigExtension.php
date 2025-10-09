<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Twig;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AbbreviationsTwigExtension extends AbstractExtension
{
    private AbbreviationRepository $abbreviationRepository;

    public function __construct(AbbreviationRepository $abbreviationRepository)
    {
        $this->abbreviationRepository = $abbreviationRepository;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('sulu_resolve_abbreviation', [$this, 'resolveAbbreviation']),
            new TwigFunction('sulu_get_abbreviations', [$this, 'getAbbreviations'])
        ];
    }

    public function resolveAbbreviation(int $id, string $locale = 'en'): ?Abbreviation
    {
        $abbreviation = $this->abbreviationRepository->findById($id, $locale);

        return $abbreviation ?? null;
    }

    public function getAbbreviations(int $limit = 100, $locale = 'en')
    {
        return $this->abbreviationRepository->findByFilters([], 0, $limit, $limit, $locale);
    }
}