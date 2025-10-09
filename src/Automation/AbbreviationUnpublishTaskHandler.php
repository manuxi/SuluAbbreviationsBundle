<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Automation;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationUnpublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationUnpublishedEvent as AbbreviationUnpublishedEventForSearch;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\TaskHandlerConfiguration;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AbbreviationUnpublishTaskHandler implements AutomationTaskHandlerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private DomainEventCollectorInterface $domainEventCollector,
        private EventDispatcherInterface $dispatcher
    ) {}

    public function handle($workload): void
    {
        if (!\is_array($workload)) {
            return;
        }
        $class = $workload['class'];
        $repository = $this->entityManager->getRepository($class);
        $entity = $repository->findById((int)$workload['id'], $workload['locale']);
        if ($entity === null) {
            return;
        }

        $entity->setPublished(false);

        $this->domainEventCollector->collect(
            new AbbreviationUnpublishedEvent($entity, $workload)
        );

        $repository->save($entity);

        $this->dispatcher->dispatch(new AbbreviationUnpublishedEventForSearch($entity));
    }

    public function configureOptionsResolver(OptionsResolver $optionsResolver): OptionsResolver
    {
        return $optionsResolver->setRequired(['id', 'locale'])
            ->setAllowedTypes('id', 'string')
            ->setAllowedTypes('locale', 'string');
    }

    public function supports(string $entityClass): bool
    {
        return $entityClass === Abbreviation::class || \is_subclass_of($entityClass, Abbreviation::class);
    }

    public function getConfiguration(): TaskHandlerConfiguration
    {
        return TaskHandlerConfiguration::create($this->translator->trans("sulu_news.unpublish", [], 'admin'));
    }
}
