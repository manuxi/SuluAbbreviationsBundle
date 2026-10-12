<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Content\Type;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\SimpleContentType;

class AbbreviationsSelection extends SimpleContentType
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
    ) {
        parent::__construct('abbreviation_selection', []);
    }

    /**
     * Returns the published abbreviations of the current locale in the order of the selection.
     *
     * @return Abbreviation[]
     */
    public function getContentData(PropertyInterface $property): array
    {
        $ids = $property->getValue();

        if (empty($ids) || !\is_array($ids)) {
            return [];
        }

        $ids    = array_values(array_map('intval', $ids));
        $locale = $property->getStructure()->getLanguageCode();

        /** @var Abbreviation[] $abbreviations */
        $abbreviations = $this->entityManager->getRepository(Abbreviation::class)->findBy(['id' => $ids]);

        $abbreviations = array_values(array_filter(
            $abbreviations,
            static fn (Abbreviation $abbreviation): bool => true === $abbreviation->setLocale($locale)->isPublished()
        ));

        $idPositions = array_flip($ids);
        usort(
            $abbreviations,
            static fn (Abbreviation $a, Abbreviation $b): int => $idPositions[$a->getId()] <=> $idPositions[$b->getId()]
        );

        return $abbreviations;
    }

    /**
     * @return array{ids: mixed}
     */
    public function getViewData(PropertyInterface $property): array
    {
        return [
            'ids' => $property->getValue(),
        ];
    }
}
