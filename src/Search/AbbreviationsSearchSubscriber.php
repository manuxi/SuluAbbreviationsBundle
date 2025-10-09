<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Search;

use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationPublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationSavedEvent;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationUnpublishedEvent;
use Massive\Bundle\SearchBundle\Search\SearchManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AbbreviationsSearchSubscriber implements EventSubscriberInterface
{

    public function __construct(private SearchManagerInterface $searchManager) {}

    public static function getSubscribedEvents(): array
    {
        return [
            AbbreviationPublishedEvent::class => 'onPublished',
            AbbreviationUnpublishedEvent::class => 'onUnpublished',
            AbbreviationSavedEvent::class => 'onSaved',
            AbbreviationRemovedEvent::class => 'onRemoved',
        ];
    }

    public function onPublished(AbbreviationPublishedEvent $event): void
    {
        $entity = $event->getEntity();
        if($entity->isPublished()) {
            $this->searchManager->index($entity);
        }
    }

    public function onUnpublished(AbbreviationUnpublishedEvent $event): void
    {
        $this->searchManager->deindex($event->getEntity());
    }

    public function onSaved(AbbreviationSavedEvent $event): void
    {
        $entity = $event->getEntity();
        if($entity->isPublished()) {
            $this->searchManager->index($entity);
        } else {
            $this->searchManager->deindex($entity);
        }
    }

    public function onRemoved(AbbreviationRemovedEvent $event): void
    {
        $this->searchManager->deindex($event->getEntity());
    }
}