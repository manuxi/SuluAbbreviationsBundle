<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationsSettings;
use Manuxi\SuluAbbreviationsBundle\Twig\AbbreviationsSettingsTwigExtension;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class AbbreviationsSettingsTwigExtensionTest extends TestCase
{
    use ProphecyTrait;

    public function testRegistersFunction(): void
    {
        $extension = new AbbreviationsSettingsTwigExtension($this->prophesize(EntityManagerInterface::class)->reveal());

        $this->assertSame('load_abbreviations_settings', $extension->getFunctions()[0]->getName());
    }

    public function testReturnsStoredSettings(): void
    {
        $settings   = new AbbreviationsSettings();
        $repository = $this->prophesize(ObjectRepository::class);
        $repository->findOneBy([])->willReturn($settings);
        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getRepository(AbbreviationsSettings::class)->willReturn($repository->reveal());

        $extension = new AbbreviationsSettingsTwigExtension($entityManager->reveal());

        $this->assertSame($settings, $extension->loadAbbreviationsSettings());
    }

    public function testReturnsNewSettingsWhenNothingIsStored(): void
    {
        $repository = $this->prophesize(ObjectRepository::class);
        $repository->findOneBy([])->willReturn(null);
        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getRepository(AbbreviationsSettings::class)->willReturn($repository->reveal());

        $extension = new AbbreviationsSettingsTwigExtension($entityManager->reveal());

        $this->assertInstanceOf(AbbreviationsSettings::class, $extension->loadAbbreviationsSettings());
    }
}
