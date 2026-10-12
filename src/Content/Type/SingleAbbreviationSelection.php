<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Content\Type;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Resolver\AbbreviationResolver;
use Sulu\Bundle\ReferenceBundle\Application\Collector\ReferenceCollectorInterface;
use Sulu\Bundle\ReferenceBundle\Infrastructure\Sulu\ContentType\ReferenceContentTypeInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\SimpleContentType;

class SingleAbbreviationSelection extends SimpleContentType implements ReferenceContentTypeInterface
{
    public function __construct(
        private readonly AbbreviationResolver $resolver,
    ) {
        parent::__construct('single_abbreviation_selection');
    }

    /**
     * Returns the selected abbreviation if it is published in the current locale.
     *
     * @return array<string, mixed>|null
     */
    public function getContentData(PropertyInterface $property): ?array
    {
        $id = $property->getValue();

        if (!\is_string($id) || '' === $id) {
            return null;
        }

        return $this->resolver->resolve($id, $property->getStructure()->getLanguageCode());
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

    public function getReferences(PropertyInterface $property, ReferenceCollectorInterface $referenceCollector, string $propertyPrefix = ''): void
    {
        $id = $property->getValue();
        if (!\is_string($id) || '' === $id) {
            return;
        }

        $referenceCollector->addReference(Abbreviation::RESOURCE_KEY, $id, $propertyPrefix . $property->getName());
    }
}
