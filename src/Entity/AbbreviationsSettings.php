<?php

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sulu\Component\Persistence\Model\AuditableInterface;
use Sulu\Component\Persistence\Model\AuditableTrait;

/**
 * @ORM\Entity()
 * @ORM\Table(name="app_abbreviation_settings")
 */
class AbbreviationsSettings implements AuditableInterface
{
    use AuditableTrait;

    public const RESOURCE_KEY = 'abbreviations_settings';
    public const FORM_KEY = 'abbreviations_config';
    public const SECURITY_CONTEXT = 'sulu.abbreviations.settings';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private ?bool $toggleHeader = null;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private ?bool $toggleHero = null;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private ?bool $toggleBreadcrumbs = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $pageAbbreviations = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToggleHeader(): ?bool
    {
        return $this->toggleHeader;
    }

    public function setToggleHeader(?bool $toggleHeader): void
    {
        $this->toggleHeader = $toggleHeader;
    }

    public function getToggleHero(): ?bool
    {
        return $this->toggleHero;
    }

    public function setToggleHero(?bool $toggleHero): void
    {
        $this->toggleHero = $toggleHero;
    }

    public function getToggleBreadcrumbs(): ?bool
    {
        return $this->toggleBreadcrumbs;
    }

    public function setToggleBreadcrumbs(?bool $toggleBreadcrumbs): void
    {
        $this->toggleBreadcrumbs = $toggleBreadcrumbs;
    }

    public function getPageAbbreviations(): ?string
    {
        return $this->pageAbbreviations;
    }

    public function setPageAbbreviations(?string $pageAbbreviations): void
    {
        $this->pageAbbreviations = $pageAbbreviations;
    }

}