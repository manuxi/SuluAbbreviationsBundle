<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\PublishedTranslatableTrait;
use Manuxi\SuluAbbreviationsBundle\Entity\Interfaces\AuditableTranslatableInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\AuditableTranslatableTrait;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationTranslation;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\ImageTranslatableTrait;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\RouteTranslatableTrait;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\UrlTranslatableTrait;

/**
 * @ORM\Entity
 * @ORM\Table(name="app_abbreviation")
 * @ORM\Entity(repositoryClass=AbbreviationRepository::class)
 */
class Abbreviation implements AuditableTranslatableInterface
{
    public const RESOURCE_KEY = 'abbreviations';
    public const FORM_KEY = 'abbreviation_details';
    public const LIST_KEY = 'abbreviations';
    public const SECURITY_CONTEXT = 'sulu.abbreviations.abbreviations';

    use AuditableTranslatableTrait;
    use PublishedTranslatableTrait;
    use RouteTranslatableTrait;
    use UrlTranslatableTrait;
    use ImageTranslatableTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\OneToMany(targetEntity=AbbreviationTranslation::class, mappedBy="abbreviation", cascade={"ALL"}, indexBy="locale", fetch="EXTRA_LAZY")
     * @Serializer\Exclude
     */
    private Collection $translations;

    private string $locale = 'en';

    private array $ext = [];

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @Serializer\VirtualProperty(name="name")
     */
    public function getName(): ?string
    {
        $translation = $this->getTranslation($this->locale);
        if (!$translation) {
            return null;
        }

        return $translation->getName();
    }

    public function setName(?string $name): self
    {
        $translation = $this->getTranslation($this->locale);
        if (!$translation) {
            $translation = $this->createTranslation($this->locale);
        }

        $translation->setName($name);
        return $this;
    }

    /**
     * @Serializer\VirtualProperty(name="explanation")
     */
    public function getExplanation(): ?string
    {
        $translation = $this->getTranslation($this->locale);
        if (!$translation) {
            return null;
        }

        return $translation->getExplanation();
    }

    public function setExplanation(?string $explanation): self
    {
        $translation = $this->getTranslation($this->locale);
        if (!$translation) {
            $translation = $this->createTranslation($this->locale);
        }

        $translation->setExplanation($explanation);
        return $this;
    }

    /**
     * @Serializer\VirtualProperty(name="description")
     */
    public function getDescription(): ?string
    {
        $translation = $this->getTranslation($this->locale);
        if (!$translation) {
            return null;
        }

        return $translation->getDescription();
    }

    public function setDescription(string $description): self
    {
        $translation = $this->getTranslation($this->locale);
        if (!$translation) {
            $translation = $this->createTranslation($this->locale);
        }

        $translation->setDescription($description);
        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * @return AbbreviationTranslation[]
     */
    public function getTranslations(): array
    {
        return $this->translations->toArray();
    }

    protected function getTranslation(string $locale): ?AbbreviationTranslation
    {
        if (!$this->translations->containsKey($locale)) {
            return null;
        }

        return $this->translations->get($locale);
    }

    protected function createTranslation(string $locale): AbbreviationTranslation
    {
        $translation = new AbbreviationTranslation($this, $locale);
        $this->translations->set($locale, $translation);
        return $translation;
    }

    /**
     * @Serializer\VirtualProperty("availableLocales")
     */
    public function getAvailableLocales(): array
    {
        return \array_values($this->translations->getKeys());
    }

    /**
     * @todo implement opject cloning/copy
     * @return $this|null
     */
    public function copy(): ?static
    {
        return null;
    }

    public function copyToLocale(string $locale): self
    {
        if ($currentTranslation = $this->getTranslation($this->getLocale())) {
           $newTranslation = clone $currentTranslation;
           $newTranslation->setLocale($locale);
           $this->translations->set($locale, $newTranslation);

           //copy ext also...
           foreach($this->ext as $translatable) {
               $translatable->copyToLocale($locale);
           }

           $this->setLocale($locale);
        }
        return $this;
    }

}
