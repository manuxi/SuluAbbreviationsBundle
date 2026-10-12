<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Entity;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationVersion;
use PHPUnit\Framework\TestCase;

class AbbreviationVersionTest extends TestCase
{
    public function testAccessors(): void
    {
        $abbreviation = new Abbreviation();
        $created      = new \DateTimeImmutable('2026-01-02 03:04:05');
        $version      = new AbbreviationVersion($abbreviation, 'en', 1767323045, ['name' => 'HTML'], $created);

        $this->assertNull($version->getId());
        $this->assertSame($abbreviation, $version->getAbbreviation());
        $this->assertSame('en', $version->getLocale());
        $this->assertSame(1767323045, $version->getVersion());
        $this->assertSame(['name' => 'HTML'], $version->getData());
        $this->assertSame($created, $version->getCreated());
        $this->assertNull($version->getCreator());
    }

    public function testCreatedDefaultsToNow(): void
    {
        $before  = new \DateTimeImmutable();
        $version = new AbbreviationVersion(new Abbreviation(), 'de', 1, []);

        $this->assertGreaterThanOrEqual($before, $version->getCreated());
    }
}
