<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Versioning;

use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationVersion;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationVersionRepository;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

class VersionRecorder
{
    public function __construct(
        private readonly AbbreviationVersionRepository $versionRepository,
        private readonly ContentManagerInterface $contentManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly int $maxVersions,
    ) {
    }

    /**
     * Stores a snapshot of the draft content which is published and removes the versions exceeding the retention.
     */
    public function onPublish(CompletedEvent $event): void
    {
        if ($this->maxVersions < 1) {
            return;
        }

        $dimensionContent = $event->getSubject();
        if (!$dimensionContent instanceof AbbreviationDimensionContent) {
            return;
        }

        $locale = $dimensionContent->getLocale();
        if (null === $locale) {
            return;
        }

        $abbreviation = $dimensionContent->getResource();
        $latest       = $this->versionRepository->findLatestVersionNumber($abbreviation, $locale);
        $version      = max(time(), null === $latest ? 0 : $latest + 1);

        $user = $this->tokenStorage->getToken()?->getUser();

        $this->versionRepository->add(new AbbreviationVersion(
            $abbreviation,
            $locale,
            $version,
            $this->contentManager->normalize($dimensionContent),
            new \DateTimeImmutable('@' . $version),
            $user instanceof User ? $user : null
        ));

        $this->versionRepository->removeOldVersions($abbreviation, $locale, $this->maxVersions - 1);
    }
}
