<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationExcerptTranslationRepository;
use Manuxi\SuluSharedToolsBundle\Entity\Abstracts\Entity\AbstractExcerptTranslation;
use Manuxi\SuluSharedToolsBundle\Entity\Interfaces\ExcerptTranslationInterface;

#[ORM\Entity(repositoryClass: AbbreviationExcerptTranslationRepository::class)]
#[ORM\Table(name: 'app_abbreviation_excerpt_translation')]
class AbbreviationExcerptTranslation extends AbstractExcerptTranslation implements ExcerptTranslationInterface
{
    #[JoinTable(name: 'app_abbreviation_excerpt_categories')]
    protected ?Collection $categories = null;

    #[JoinTable(name: 'app_abbreviation_excerpt_tags')]
    protected ?Collection $tags = null;

    #[JoinTable(name: 'app_abbreviation_excerpt_icons')]
    protected ?Collection $icons = null;

    #[JoinTable(name: 'app_abbreviation_excerpt_images')]
    protected ?Collection $images = null;

    #[ORM\ManyToOne(targetEntity: AbbreviationExcerpt::class, inversedBy: 'translations')]
    #[JoinColumn(nullable: false)]
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
