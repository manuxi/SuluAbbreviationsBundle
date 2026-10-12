<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use Sulu\Bundle\SecurityBundle\Entity\User;

class AbbreviationVersion
{
    private ?int $id = null;

    private Abbreviation $abbreviation;

    private string $locale;

    private int $version;

    /**
     * @var array<string, mixed>
     */
    private array $data;

    private \DateTimeImmutable $created;

    private ?User $creator;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Abbreviation $abbreviation,
        string $locale,
        int $version,
        array $data,
        ?\DateTimeImmutable $created = null,
        ?User $creator = null,
    ) {
        $this->abbreviation = $abbreviation;
        $this->locale       = $locale;
        $this->version      = $version;
        $this->data         = $data;
        $this->created      = $created ?? new \DateTimeImmutable();
        $this->creator      = $creator;
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

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getCreated(): \DateTimeImmutable
    {
        return $this->created;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }
}
