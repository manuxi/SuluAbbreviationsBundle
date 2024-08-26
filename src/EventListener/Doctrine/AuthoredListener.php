<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\EventListener\Doctrine;

use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Manuxi\SuluAbbreviationsBundle\Entity\Interfaces\AuthoredInterface;

class AuthoredListener
{
    const AUTHORED_PROPERTY_NAME = 'authored';

    /**
     * Load the class data, mapping the created and changed fields
     * to datetime fields.
     * @param LoadClassMetadataEventArgs $abbreviation
     * @throws MappingException
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $abbreviation)
    {
        $metadata = $abbreviation->getClassMetadata();
        $reflection = $metadata->getReflectionClass();

        if (null !== $reflection && $reflection->implementsInterface('Manuxi\SuluAbbreviationsBundle\Entity\Interfaces\AuthoredInterface')) {
            if (!$metadata->hasField(self::AUTHORED_PROPERTY_NAME)) {
                $metadata->mapField([
                    'fieldName' => self::AUTHORED_PROPERTY_NAME,
                    'type' => 'datetime',
                    'notnull' => true,
                ]);
            }
        }
    }

    /**
     * Set the timestamps before update.
     * @param LifecycleEventArgs $abbreviation
     */
    public function preUpdate(LifecycleEventArgs $abbreviation)
    {
        $this->handleTimestamp($abbreviation);
    }

    /**
     * Set the timestamps before creation.
     * @param LifecycleEventArgs $abbreviation
     */
    public function prePersist(LifecycleEventArgs $abbreviation)
    {
        $this->handleTimestamp($abbreviation);
    }

    /**
     * Set the timestamps. If created is NULL then set it. Always
     * set the changed field.
     * @param LifecycleEventArgs $abbreviation
     */
    private function handleTimestamp(LifecycleEventArgs $abbreviation)
    {
        $entity = $abbreviation->getObject();

        if (!$entity instanceof AuthoredInterface) {
            return;
        }

        $meta = $abbreviation->getObjectManager()->getClassMetadata(\get_class($entity));

        $authored = $meta->getFieldValue($entity, self::AUTHORED_PROPERTY_NAME);
        if (null === $authored) {
            $meta->setFieldValue($entity, self::AUTHORED_PROPERTY_NAME, new \DateTime());
        }
    }
}
