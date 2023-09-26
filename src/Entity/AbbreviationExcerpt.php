<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use JMS\Serializer\Annotation as Serializer;
use Manuxi\SuluAbbreviationsBundle\Entity\Interfaces\ExcerptInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Interfaces\ExcerptTranslatableInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\ExcerptTrait;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\ExcerptTranslatableTrait;

/**
 * @ORM\Entity
 * @ORM\Table(name="app_abbreviation_excerpt")
 * @ORM\Entity(repositoryClass="Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationExcerptRepository")
 */
class AbbreviationExcerpt implements ExcerptInterface, ExcerptTranslatableInterface
{
    use ExcerptTrait;
    use ExcerptTranslatableTrait;

    /**
     * @ORM\OneToOne(targetEntity="Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation", inversedBy="abbreviationExcerpt", cascade={"persist", "remove"})
     * @JoinColumn(name="abbreviation_id", referencedColumnName="id", nullable=false)
     *
     * @Serializer\Exclude
     */
    private ?Abbreviation $abbreviation = null;

    /**
     * @ORM\OneToMany(targetEntity="AbbreviationExcerptTranslation", mappedBy="abbreviationExcerpt", cascade={"ALL"}, indexBy="locale")
     *
     * @Serializer\Exclude
     */
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
     * @return AbbreviationExcerptTranslation[]
     */
    public function getTranslations(): array
    {
        return $this->translations->toArray();
    }

    protected function getTranslation(string $locale): ?AbbreviationExcerptTranslation
    {
        if (!$this->translations->containsKey($locale)) {
            return null;
        }

        return $this->translations->get($locale);
    }

    protected function createTranslation(string $locale): AbbreviationExcerptTranslation
    {
        $translation = new AbbreviationExcerptTranslation($this, $locale);
        $this->translations->set($locale, $translation);

        return $translation;
    }

}
