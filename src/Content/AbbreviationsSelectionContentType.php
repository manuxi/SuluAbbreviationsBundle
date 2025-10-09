<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Content;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\SimpleContentType;

class AbbreviationsSelectionContentType extends SimpleContentType
{
    private AbbreviationRepository $abbreviationRepository;

    public function __construct(AbbreviationRepository $abbreviationRepository)
    {
        parent::__construct('abbreviation_selection');

        $this->abbreviationRepository = $abbreviationRepository;
    }

    /**
     * @param PropertyInterface $property
     * @return Abbreviation[]
     */
    public function getContentData(PropertyInterface $property): array
    {
        $ids = $property->getValue();
        $locale = $property->getStructure()->getLanguageCode();

        $abbreviations = [];
        foreach ($ids ?: [] as $id) {
            $abbreviation = $this->abbreviationRepository->findById((int) $id, $locale);
            if ($abbreviation && $abbreviation->isPublished()) {
                $abbreviations[] = $abbreviation;
            }
        }
        return $abbreviations;
    }

    public function getViewData(PropertyInterface $property): mixed
    {
        return $property->getValue();
    }
}
