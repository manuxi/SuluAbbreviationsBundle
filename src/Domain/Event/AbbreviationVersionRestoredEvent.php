<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Domain\Event;

class AbbreviationVersionRestoredEvent extends AbstractAbbreviationEvent
{
    public function getEventType(): string
    {
        return 'version_restored';
    }
}
