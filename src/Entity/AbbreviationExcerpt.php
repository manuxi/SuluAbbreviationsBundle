<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use JMS\Serializer\Annotation as Serializer;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationExcerptRepository;
use Manuxi\SuluSharedToolsBundle\Entity\Interfaces\ExcerptInterface;
use Manuxi\SuluSharedToolsBundle\Entity\Interfaces\ExcerptTranslatableInterface;
use Manuxi\SuluSharedToolsBundle\Entity\Traits\ExcerptTrait;
use Manuxi\SuluSharedToolsBundle\Entity\Traits\ExcerptTranslatableTrait;

#[ORM\Entity(repositoryClass: AbbreviationExcerptRepository::class)]
#[ORM\Table(name: 'app_abbreviation_excerpt')]
class AbbreviationExcerpt implements ExcerptInterface, ExcerptTranslatableInterface
{
    use ExcerptTrait;
    use ExcerptTranslatableTrait;

    #[Serializer\Exclude]
    #[ORM\OneToOne(inversedBy: 'abbreviationExcerpt', targetEntity: Abbreviation::class, cascade: ['persist', 'remove'])]
    #[JoinColumn(name: 'abbreviation_id', referencedColumnName: 'id', nullable: false)]
    private ?Abbreviation $abbreviation = null;

    #[Serializer\Exclude]
    #[ORM\OneToMany(mappedBy: 'abbreviationExcerpt', targetEntity: AbbreviationExcerptTranslation::class, cascade: ['all'], indexBy: 'locale')]
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
