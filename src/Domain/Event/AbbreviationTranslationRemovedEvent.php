<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Domain\Event;

class AbbreviationTranslationRemovedEvent extends AbstractAbbreviationEvent
{
    public function getEventType(): string
    {
        return 'translation_removed';
    }
}
