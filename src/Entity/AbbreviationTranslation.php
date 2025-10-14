<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Manuxi\SuluAbbreviationsBundle\Entity\Interfaces\AuditableInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\AuditableTrait;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\ImageTrait;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\LinkTrait;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\PublishedTrait;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\RouteTrait;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\ShowAuthorTrait;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\ShowDateTrait;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationTranslationRepository;

#[ORM\Entity(repositoryClass: AbbreviationTranslationRepository::class)]
#[ORM\Table(name: 'app_abbreviation_translation')]
class AbbreviationTranslation implements AuditableInterface
{
    use AuditableTrait;
    use ImageTrait;
    use LinkTrait;
    use PublishedTrait;
    use RouteTrait;
    use ShowAuthorTrait;
    use ShowDateTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Abbreviation::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private Abbreviation $abbreviation;

    #[ORM\Column(type: Types::STRING, length: 5)]
    private string $locale;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $explanation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function __construct(Abbreviation $abbreviation, string $locale)
    {
        $this->abbreviation = $abbreviation;
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
