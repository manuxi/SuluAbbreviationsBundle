<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Content\Type;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\SimpleContentType;

class SingleAbbreviationSelection extends SimpleContentType
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
    ) {
        parent::__construct('single_abbreviation_selection');
    }

    /**
     * Returns the selected abbreviation if it is published in the current locale.
     */
    public function getContentData(PropertyInterface $property): ?Abbreviation
    {
        $id = $property->getValue();

        if (empty($id)) {
            return null;
        }

        $abbreviation = $this->entityManager->getRepository(Abbreviation::class)->find((int) $id);
        if (!$abbreviation instanceof Abbreviation) {
            return null;
        }

        $abbreviation->setLocale($property->getStructure()->getLanguageCode());

        return true === $abbreviation->isPublished() ? $abbreviation : null;
    }

    /**
     * @return array{id: mixed}
     */
    public function getViewData(PropertyInterface $property): array
    {
        return [
            'id' => $property->getValue(),
        ];
    }
}
