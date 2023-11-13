<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Domain\Event\Settings;

use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationsSettings;
use Sulu\Bundle\ActivityBundle\Domain\Event\DomainEvent;

abstract class AbstractEvent extends DomainEvent
{
    private AbbreviationsSettings $entity;
    private array $payload = [];

    public function __construct(AbbreviationsSettings $entity)
    {
        parent::__construct();
        $this->entity = $entity;
    }

    public function getEvent(): AbbreviationsSettings
    {
        return $this->entity;
    }

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
        return (string)$this->entity->getId();
    }

    public function getResourceTitle(): ?string
    {
        return "Abbreviations Settings";
    }

    public function getResourceSecurityContext(): ?string
    {
        return AbbreviationsSettings::SECURITY_CONTEXT;
    }
}
