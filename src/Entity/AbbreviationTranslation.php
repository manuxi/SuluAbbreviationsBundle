<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Manuxi\SuluAbbreviationsBundle\Entity\Interfaces\AuditableInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\AuditableTrait;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\LinkTrait;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationTranslationRepository;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\ImageTrait;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\PublishedTrait;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\RouteTrait;

/**
 * @ORM\Entity
 * @ORM\Table(name="app_abbreviation_translation")
 * @ORM\Entity(repositoryClass=AbbreviationTranslationRepository::class)
 */
class AbbreviationTranslation implements AuditableInterface
{
    use AuditableTrait;
    use PublishedTrait;
    use RouteTrait;
    use LinkTrait;
    use ImageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id;

    /**
     * @ORM\ManyToOne(targetEntity=Abbreviation::class, inversedBy="translations")
     * @ORM\JoinColumn(nullable=false)
     */
    private Abbreviation $abbreviation;

    /**
     * @ORM\Column(type="string", length=5)
     */
    private string $locale;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $explanation = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    public function __construct(Abbreviation $abbreviation, string $locale)
    {
        $this->abbreviation  = $abbreviation;
        $this->locale = $locale;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAbbreviation(): Abbreviation
    {
        return $this->abbreviation;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function setExplanation(?string $explanation): self
    {
        $this->explanation = $explanation;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

}
