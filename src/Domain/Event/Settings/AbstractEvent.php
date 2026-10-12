<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Domain\Event\Settings;

use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationsSettings;
use Sulu\Bundle\ActivityBundle\Domain\Event\DomainEvent;

abstract class AbstractEvent extends DomainEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly AbbreviationsSettings $entity,
        private readonly array $payload = [],
    ) {
        parent::__construct();
    }

    public function getEvent(): AbbreviationsSettings
    {
        return $this->entity;
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
        return AbbreviationsSettings::RESOURCE_KEY;
    }

    public function getResourceId(): string
    {
        return (string) $this->entity->getId();
    }

    public function getResourceTitle(): ?string
    {
        return 'Abbreviations Settings';
    }

    public function getResourceSecurityContext(): ?string
    {
        return AbbreviationsSettings::SECURITY_CONTEXT;
    }
}
