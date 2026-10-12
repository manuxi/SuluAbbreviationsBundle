<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Domain\Event;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Bundle\ActivityBundle\Domain\Event\DomainEvent;

abstract class AbstractAbbreviationEvent extends DomainEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $abbreviationId,
        private readonly ?string $title = null,
        private readonly ?string $locale = null,
        private readonly array $payload = [],
    ) {
        parent::__construct();
    }

    public function getAbbreviationId(): string
    {
        return $this->abbreviationId;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEventPayload(): ?array
    {
        return $this->payload;
    }

    public function getResourceKey(): string
    {
        return Abbreviation::RESOURCE_KEY;
    }

    public function getResourceId(): string
    {
        return $this->abbreviationId;
    }

    public function getResourceLocale(): ?string
    {
        return $this->locale;
    }

    public function getResourceTitle(): ?string
    {
        return $this->title;
    }

    public function getResourceTitleLocale(): ?string
    {
        return $this->locale;
    }

    public function getResourceSecurityContext(): ?string
    {
        return Abbreviation::SECURITY_CONTEXT;
    }
}
