<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Reference;

use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Sulu\Component\HttpKernel\SuluKernel;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Keeps the references in sync with the workflow transitions (also for transitions triggered by the automation).
 */
class AbbreviationReferenceSubscriber
{
    public function __construct(
        private readonly AbbreviationReferenceUpdater $referenceUpdater,
    ) {
    }

    public function onPublish(CompletedEvent $event): void
    {
        $dimensionContent = $event->getSubject();
        if ($dimensionContent instanceof AbbreviationDimensionContent) {
            $this->referenceUpdater->update($dimensionContent, SuluKernel::CONTEXT_WEBSITE);
        }
    }

    public function onUnpublish(CompletedEvent $event): void
    {
        $dimensionContent = $event->getSubject();
        if ($dimensionContent instanceof AbbreviationDimensionContent && null !== $dimensionContent->getLocale()) {
            $this->referenceUpdater->remove($dimensionContent->getResource(), $dimensionContent->getLocale(), SuluKernel::CONTEXT_WEBSITE);
        }
    }

    public function onRemoveDraft(CompletedEvent $event): void
    {
        $dimensionContent = $event->getSubject();
        if ($dimensionContent instanceof AbbreviationDimensionContent) {
            $this->referenceUpdater->update($dimensionContent, SuluKernel::CONTEXT_ADMIN);
        }
    }
}
