<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Manuxi\SuluAbbreviationsBundle\Entity\Interfaces\ExcerptTranslationInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\ExcerptTranslationTrait;

/**
 * @ORM\Entity
 * @ORM\Table(name="app_abbreviation_excerpt_translation")
 * @ORM\Entity(repositoryClass="Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationExcerptTranslationRepository")
 */
class AbbreviationExcerptTranslation implements ExcerptTranslationInterface
{
    use ExcerptTranslationTrait;

    /**
     * @ORM\ManyToOne(targetEntity="Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationExcerpt", inversedBy="translations")
     * @ORM\JoinColumn(nullable=false)
     */
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
