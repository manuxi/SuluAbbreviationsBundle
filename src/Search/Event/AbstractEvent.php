<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Search\Event;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Symfony\Contracts\EventDispatcher\Event as SymfonyEvent;

abstract class AbstractEvent extends SymfonyEvent
{
    public function __construct(public Abbreviation $entity) {}

    public function getEntity(): Abbreviation
    {
        return $this->entity;
    }
}