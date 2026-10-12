<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Reference;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;
use Sulu\Bundle\ReferenceBundle\Application\Refresh\ReferenceRefresherInterface;
use Sulu\Component\HttpKernel\SuluKernel;

/**
 * Rebuilds the references with `bin/console sulu:reference:refresh abbreviations`:
 * the admin context uses the draft content, the website context the live content.
 */
class AbbreviationReferenceRefresher implements ReferenceRefresherInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentManagerInterface $contentManager,
        private readonly AbbreviationReferenceUpdater $referenceUpdater,
        private readonly string $suluContext,
    ) {
    }

    public static function getResourceKey(): string
    {
        return Abbreviation::RESOURCE_KEY;
    }

    public function refresh(): \Generator
    {
        $stage = SuluKernel::CONTEXT_WEBSITE === $this->suluContext
            ? DimensionContentInterface::STAGE_LIVE
            : DimensionContentInterface::STAGE_DRAFT;

        /** @var iterable<Abbreviation> $abbreviations */
        $abbreviations = $this->entityManager->getRepository(Abbreviation::class)->createQueryBuilder('abbreviation')
            ->getQuery()
            ->toIterable();

        foreach ($abbreviations as $abbreviation) {
            foreach ($this->getLocales($abbreviation) as $locale) {
                try {
                    $dimensionContent = $this->contentManager->resolve($abbreviation, ['locale' => $locale, 'stage' => $stage]);
                } catch (ContentNotFoundException) {
                    continue;
                }

                if ($dimensionContent->getLocale() !== $locale || $dimensionContent->getStage() !== $stage) {
                    continue;
                }

                $this->referenceUpdater->update($dimensionContent, AbbreviationReferenceUpdater::getContext($stage));
            }

            yield $abbreviation;
        }
    }

    /**
     * @return string[]
     */
    private function getLocales(Abbreviation $abbreviation): array
    {
        $locales = [];
        foreach ($abbreviation->getDimensionContents() as $dimensionContent) {
            if (null === $dimensionContent->getLocale()) {
                foreach ($dimensionContent->getAvailableLocales() ?? [] as $locale) {
                    $locales[$locale] = $locale;
                }
            }
        }

        return array_values($locales);
    }
}
