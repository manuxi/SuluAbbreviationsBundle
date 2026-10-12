<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Content\Type;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Resolver\AbbreviationResolver;
use Sulu\Bundle\ReferenceBundle\Application\Collector\ReferenceCollectorInterface;
use Sulu\Bundle\ReferenceBundle\Infrastructure\Sulu\ContentType\ReferenceContentTypeInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\SimpleContentType;

class AbbreviationsSelection extends SimpleContentType implements ReferenceContentTypeInterface
{
    public function __construct(
        private readonly AbbreviationResolver $resolver,
    ) {
        parent::__construct('abbreviation_selection', []);
    }

    /**
     * Returns the published abbreviations of the current locale in the order of the selection.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getContentData(PropertyInterface $property): array
    {
        $ids = $property->getValue();

        if (empty($ids) || !\is_array($ids)) {
            return [];
        }

        return $this->resolver->resolveMany(array_values($ids), $property->getStructure()->getLanguageCode());
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

    public function getReferences(PropertyInterface $property, ReferenceCollectorInterface $referenceCollector, string $propertyPrefix = ''): void
    {
        $ids = $property->getValue();
        if (!\is_array($ids)) {
            return;
        }

        foreach ($ids as $id) {
            if (!\is_string($id) || '' === $id) {
                continue;
            }

            $referenceCollector->addReference(Abbreviation::RESOURCE_KEY, $id, $propertyPrefix . $property->getName());
        }
    }
}
