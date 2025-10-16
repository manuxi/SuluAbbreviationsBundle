<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Manuxi\SuluSharedToolsBundle\Entity\Interfaces\SeoTranslationInterface;
use Manuxi\SuluSharedToolsBundle\Entity\Traits\SeoTranslationTrait;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationSeoTranslationRepository;

#[ORM\Entity(repositoryClass: AbbreviationSeoTranslationRepository::class)]
#[ORM\Table(name: 'app_abbreviation_seo_translation')]
class AbbreviationSeoTranslation implements SeoTranslationInterface
{
    use SeoTranslationTrait;

    #[ORM\ManyToOne(targetEntity: AbbreviationSeo::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private AbbreviationSeo $abbreviationSeo;

    public function __construct(AbbreviationSeo $abbreviationSeo, string $locale)
    {
        $this->abbreviationSeo = $abbreviationSeo;
        $this->setLocale($locale);
    }

    public function getSeo(): AbbreviationSeo
    {
        return $this->abbreviationSeo;
    }

}
