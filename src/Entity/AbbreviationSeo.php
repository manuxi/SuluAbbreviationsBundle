<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use JMS\Serializer\Annotation as Serializer;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationSeoRepository;
use Manuxi\SuluSharedToolsBundle\Entity\Interfaces\SeoInterface;
use Manuxi\SuluSharedToolsBundle\Entity\Interfaces\SeoTranslatableInterface;
use Manuxi\SuluSharedToolsBundle\Entity\Traits\SeoTrait;
use Manuxi\SuluSharedToolsBundle\Entity\Traits\SeoTranslatableTrait;

#[ORM\Entity(repositoryClass: AbbreviationSeoRepository::class)]
#[ORM\Table(name: 'app_abbreviation_seo')]
class AbbreviationSeo implements SeoInterface, SeoTranslatableInterface
{
    use SeoTrait;
    use SeoTranslatableTrait;

    #[Serializer\Exclude]
    #[ORM\OneToOne(inversedBy: 'abbreviationSeo', targetEntity: Abbreviation::class, cascade: ['persist', 'remove'])]
    #[JoinColumn(name: 'abbreviation_id', referencedColumnName: 'id', nullable: false)]
    private ?Abbreviation $abbreviation = null;

    #[Serializer\Exclude]
    #[ORM\OneToMany(mappedBy: 'abbreviationSeo', targetEntity: AbbreviationSeoTranslation::class, cascade: ['all'], indexBy: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getAbbreviation(): ?Abbreviation
    {
        return $this->abbreviation;
    }

    public function setAbbreviation(Abbreviation $abbreviation): self
    {
        $this->abbreviation = $abbreviation;

        return $this;
    }

    /**
     * @return AbbreviationSeoTranslation[]
     */
    public function getTranslations(): array
    {
        return $this->translations->toArray();
    }

    protected function getTranslation(string $locale): ?AbbreviationSeoTranslation
    {
        if (!$this->translations->containsKey($locale)) {
            return null;
        }

        return $this->translations->get($locale);
    }

    protected function createTranslation(string $locale): AbbreviationSeoTranslation
    {
        $translation = new AbbreviationSeoTranslation($this, $locale);
        $this->translations->set($locale, $translation);

        return $translation;
    }
}
