<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Domain\Event;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Bundle\ActivityBundle\Domain\Event\DomainEvent;

abstract class AbstractAbbreviationEvent extends DomainEvent
{
    private Abbreviation $abbreviation;
    private array $payload = [];

    public function __construct(Abbreviation $abbreviation, array $payload)
    {
        parent::__construct();
        $this->abbreviation = $abbreviation;
        $this->payload = $payload;
    }

    public function getAbbreviation(): Abbreviation
    {
        return $this->abbreviation;
    }

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
        return (string)$this->abbreviation->getId();
    }

    public function getResourceTitle(): ?string
    {
        return $this->abbreviation->getName();
    }

    public function getResourceSecurityContext(): ?string
    {
        return Abbreviation::SECURITY_CONTEXT;
    }
}
