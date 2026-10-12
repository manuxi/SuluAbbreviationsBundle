<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Domain\Event;

class AbbreviationUnpublishedEvent extends AbstractAbbreviationEvent
{
    public function getEventType(): string
    {
        return 'unpublished';
    }
}
