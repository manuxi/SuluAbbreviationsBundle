<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Manuxi\SuluAbbreviationsBundle\Entity\Interfaces\ExcerptTranslationInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\ExcerptTranslationTrait;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationExcerptTranslationRepository;

#[ORM\Entity(repositoryClass: AbbreviationExcerptTranslationRepository::class)]
#[ORM\Table(name: 'app_abbreviation_excerpt_translation')]
class AbbreviationExcerptTranslation implements ExcerptTranslationInterface
{
    use ExcerptTranslationTrait;

    #[ORM\ManyToOne(targetEntity: AbbreviationExcerpt::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private AbbreviationExcerpt $abbreviationExcerpt;

    public function __construct(AbbreviationExcerpt $abbreviationExcerpt, string $locale)
    {
        $this->abbreviationExcerpt = $abbreviationExcerpt;
        $this->setLocale($locale);
        $this->initExcerptTranslationTrait();
    }

    public function getExcerpt(): AbbreviationExcerpt
    {
        return $this->abbreviationExcerpt;
    }
}
