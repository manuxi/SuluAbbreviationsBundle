<?php

namespace Manuxi\SuluAbbreviationsBundle\Twig;

use Doctrine\ORM\EntityManagerInterface;

use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationsSettings;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AbbreviationsSettingsTwigExtension extends AbstractExtension
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('load_abbreviations_settings', [$this, 'loadAbbreviationsSettings']),
        ];
    }

    public function loadAbbreviationsSettings(): AbbreviationsSettings
    {
        $abbreviationsSettings = $this->entityManager->getRepository(AbbreviationsSettings::class)->findOneBy([]) ?? null;

        return $abbreviationsSettings ?: new AbbreviationsSettings();
    }
}