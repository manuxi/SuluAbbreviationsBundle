<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Domain\Event;

class AbbreviationPublishedEvent extends AbstractAbbreviationEvent
{
    public function getEventType(): string
    {
        return 'published';
    }
}
