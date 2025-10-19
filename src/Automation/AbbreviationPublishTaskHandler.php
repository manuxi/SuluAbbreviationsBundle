<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Automation;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationPublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluSharedToolsBundle\Search\Event\PreUpdatedEvent as SearchPreUpdatedEvent;
use Manuxi\SuluSharedToolsBundle\Search\Event\UpdatedEvent as SearchUpdatedEvent;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\TaskHandlerConfiguration;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AbbreviationPublishTaskHandler implements AutomationTaskHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly DomainEventCollectorInterface $domainEventCollector,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function handle($workload): void
    {
        if (!\is_array($workload)) {
            return;
        }
        $class = $workload['class'];
        $repository = $this->entityManager->getRepository($class);
        $entity = $repository->findById((int) $workload['id'], $workload['locale']);
        if (null === $entity) {
            return;
        }
        $this->dispatcher->dispatch(new SearchPreUpdatedEvent($entity));

        $entity->setPublished(true);

        $this->domainEventCollector->collect(
            new AbbreviationPublishedEvent($entity, $workload)
        );

        $repository->save($entity);

        $this->dispatcher->dispatch(new SearchUpdatedEvent($entity));
    }

    public function configureOptionsResolver(OptionsResolver $optionsResolver): OptionsResolver
    {
        return $optionsResolver->setRequired(['id', 'locale'])
            ->setAllowedTypes('id', 'string')
            ->setAllowedTypes('locale', 'string');
    }

    public function supports(string $entityClass): bool
    {
        return Abbreviation::class === $entityClass || \is_subclass_of($entityClass, Abbreviation::class);
    }

    public function getConfiguration(): TaskHandlerConfiguration
    {
        return TaskHandlerConfiguration::create($this->translator->trans('sulu_abbreviation.publish', [], 'admin'));
    }
}
