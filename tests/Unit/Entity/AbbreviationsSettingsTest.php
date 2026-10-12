<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Entity;

use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationsSettings;
use PHPUnit\Framework\TestCase;

class AbbreviationsSettingsTest extends TestCase
{
    public function testDefaults(): void
    {
        $settings = new AbbreviationsSettings();

        $this->assertNull($settings->getId());
        $this->assertNull($settings->getToggleHeader());
        $this->assertNull($settings->getToggleHero());
        $this->assertNull($settings->getToggleBreadcrumbs());
        $this->assertNull($settings->getPageAbbreviations());
    }

    public function testSetters(): void
    {
        $settings = new AbbreviationsSettings();
        $settings->setToggleHeader(true);
        $settings->setToggleHero(false);
        $settings->setToggleBreadcrumbs(true);
        $settings->setPageAbbreviations('page-uuid');

        $this->assertTrue($settings->getToggleHeader());
        $this->assertFalse($settings->getToggleHero());
        $this->assertTrue($settings->getToggleBreadcrumbs());
        $this->assertSame('page-uuid', $settings->getPageAbbreviations());
    }

    public function testConstants(): void
    {
        $this->assertSame('abbreviations_settings', AbbreviationsSettings::RESOURCE_KEY);
        $this->assertSame('sulu.abbreviations.settings', AbbreviationsSettings::SECURITY_CONTEXT);
    }
}
