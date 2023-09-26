<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Content;

use JMS\Serializer\Annotation as Serializer;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Component\SmartContent\ItemInterface;

/**
 * @Serializer\ExclusionPolicy("all")
 */
class AbbreviationDataItem implements ItemInterface
{

    private Abbreviation $entity;

    public function __construct(Abbreviation $entity)
    {
        $this->entity = $entity;
    }

    /**
     * @Serializer\VirtualProperty
     */
    public function getId(): string
    {
        return (string) $this->entity->getId();
    }

    /**
     * @Serializer\VirtualProperty
     */
    public function getTitle(): string
    {
        return (string) $this->entity->getName();
    }

    /**
     * @Serializer\VirtualProperty
     */
    public function getImage(): ?string
    {
        return null;
    }

    /**
     * @Serializer\VirtualProperty
     */
    public function getName(): string
    {
        return (string) $this->entity->getName();
    }

    /**
     * @Serializer\VirtualProperty
     */
    public function getExplanation(): string
    {
        return (string) $this->entity->getExplanation();
    }

    /**
     * @Serializer\VirtualProperty
     */
    public function getDescription(): ?string
    {
        return (string) $this->entity->getDescription();
    }

    public function getResource(): Abbreviation
    {
        return $this->entity;
    }
}
