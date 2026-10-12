<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Content\Type;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\SimpleContentType;

class AbbreviationsSelection extends SimpleContentType
{
    protected EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        parent::__construct('abbreviation_selection', []);
    }

    /**
     * @return Abbreviation[]
     */
    public function getContentData(PropertyInterface $property): array
    {
        $ids = $property->getValue();

        if (empty($ids)) {
            return [];
        }

        $abbreviation = $this->entityManager->getRepository(Abbreviation::class)->findBy(['id' => $ids]);

        $idPositions = array_flip($ids);
        usort($abbreviation, static function (Abbreviation $a, Abbreviation $b) use ($idPositions) {
            return $idPositions[$a->getId()] - $idPositions[$b->getId()];
        });

        return $abbreviation;
    }

    public function getViewData(PropertyInterface $property): array
    {
        return [
            'ids' => $property->getValue(),
        ];
    }
}
