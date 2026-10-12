<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Preview;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Mapper\AbbreviationDataMapper;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Sulu\Bundle\PreviewBundle\Preview\Object\PreviewObjectProviderInterface;

class AbbreviationsObjectProvider implements PreviewObjectProviderInterface
{
    public function __construct(
        private readonly AbbreviationRepository $repository,
        private readonly AbbreviationDataMapper $dataMapper,
    ) {
    }

    public function getObject($id, $locale): ?Abbreviation
    {
        return $this->repository->findById((int) $id, (string) $locale);
    }

    /**
     * @param Abbreviation $object
     */
    public function getId($object): string
    {
        return (string) $object->getId();
    }

    /**
     * @param Abbreviation        $object
     * @param array<string,mixed> $data
     */
    public function setValues($object, $locale, array $data): void
    {
        $object->setLocale((string) $locale);
        $this->dataMapper->mapData($object, $data);
    }

    /**
     * Abbreviations have no templates, so there is no context to switch.
     *
     * @param Abbreviation        $object
     * @param array<string,mixed> $context
     */
    public function setContext($object, $locale, array $context): Abbreviation
    {
        return $object;
    }

    /**
     * @param Abbreviation $object
     */
    public function serialize($object): string
    {
        // Initialize lazy translation collections, uninitialized collections would be serialized empty
        $object->getTranslations();
        $object->getSeo()->getTranslations();
        $object->getExcerpt()->getTranslations();

        return serialize($object);
    }

    public function deserialize($serializedObject, $objectClass): Abbreviation
    {
        return unserialize($serializedObject);
    }

    public function getSecurityContext($id, $locale): ?string
    {
        return Abbreviation::SECURITY_CONTEXT;
    }
}
