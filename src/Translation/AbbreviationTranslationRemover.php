<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Translation;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationTranslationRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Route\AbbreviationRouteRemover;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\ContentBundle\Content\Application\ContentIndexer\ContentIndexerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;

class AbbreviationTranslationRemover
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentIndexerInterface $contentIndexer,
        private readonly AbbreviationRouteRemover $routeRemover,
        private readonly DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    /**
     * Removes the draft and live content of one locale including its routes and search documents.
     *
     * Returns false and changes nothing if the locale is the last one of the abbreviation,
     * the caller has to delete the whole abbreviation in this case.
     */
    public function remove(Abbreviation $abbreviation, string $locale): bool
    {
        $remainingLocales = array_values(array_diff($this->getAvailableLocales($abbreviation), [$locale]));
        if ([] === $remainingLocales) {
            return false;
        }

        $title = null;
        foreach ($abbreviation->getDimensionContents() as $dimensionContent) {
            if ($dimensionContent->getLocale() === $locale) {
                if (DimensionContentInterface::STAGE_DRAFT === $dimensionContent->getStage()) {
                    $title = $dimensionContent->getTitle();
                }

                $abbreviation->removeDimensionContent($dimensionContent);
                $this->entityManager->remove($dimensionContent);

                continue;
            }

            if (null === $dimensionContent->getLocale()) {
                $dimensionContent->removeAvailableLocale($locale);

                if ($dimensionContent->getGhostLocale() === $locale) {
                    $dimensionContent->setGhostLocale($remainingLocales[0]);
                }
            }
        }

        $this->routeRemover->remove($abbreviation, $locale);
        $this->contentIndexer->deindex(Abbreviation::RESOURCE_KEY, $abbreviation->getId(), ['locale' => $locale]);
        $this->domainEventCollector->collect(new AbbreviationTranslationRemovedEvent($abbreviation->getId(), $title, $locale));

        return true;
    }

    /**
     * @return string[]
     */
    public function getAvailableLocales(Abbreviation $abbreviation): array
    {
        $locales = [];
        foreach ($abbreviation->getDimensionContents() as $dimensionContent) {
            if (null === $dimensionContent->getLocale()) {
                foreach ($dimensionContent->getAvailableLocales() ?? [] as $availableLocale) {
                    $locales[$availableLocale] = $availableLocale;
                }
            }
        }

        return array_values($locales);
    }
}
