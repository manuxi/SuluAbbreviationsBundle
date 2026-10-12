<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Domain\Event;

class AbbreviationDraftRemovedEvent extends AbstractAbbreviationEvent
{
    public function getEventType(): string
    {
        return 'draft_removed';
    }
}
