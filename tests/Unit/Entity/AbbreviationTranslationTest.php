<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Entity;

use DateTime;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationTranslation;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class AbbreviationTranslationTest extends SuluTestCase
{
    private ObjectProphecy $abbreviation;
    private AbbreviationTranslation $translation;
    private string $testString = "Lorem ipsum dolor sit amet, ...";

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        $this->abbreviation       = $this->prophesize(Abbreviation::class);
        $this->translation = new AbbreviationTranslation($this->abbreviation->reveal(), 'de');
    }

    public function testAbbreviation(): void
    {
        $this->assertSame($this->abbreviation->reveal(), $this->translation->getAbbreviation());
    }

    public function testLocale(): void
    {
        $this->assertSame('de', $this->translation->getLocale());
    }

    public function testName(): void
    {
        $this->assertNull($this->translation->getName());
        $this->assertSame($this->translation, $this->translation->setName($this->testString));
        $this->assertSame($this->testString, $this->translation->getName());
    }

    public function testExplanation(): void
    {
        $this->assertNull($this->translation->getExplanation());
        $this->assertSame($this->translation, $this->translation->setExplanation($this->testString));
        $this->assertSame($this->testString, $this->translation->getExplanation());
    }

    public function testDescription(): void
    {
        $this->assertNull($this->translation->getDescription());
        $this->assertSame($this->translation, $this->translation->setDescription($this->testString));
        $this->assertSame($this->testString, $this->translation->getDescription());
    }

    public function testPublished(): void
    {
        $this->assertFalse($this->translation->isPublished());
        $this->assertSame($this->translation, $this->translation->setPublished(true));
        $this->assertTrue($this->translation->isPublished());
        $this->assertSame($this->translation, $this->translation->setPublished(false));
        $this->assertFalse($this->translation->isPublished());
    }

    public function testPublishedAt(): void
    {
        $this->assertNull($this->translation->getPublishedAt());
        $this->assertSame($this->translation, $this->translation->setPublished(true));
        $this->assertNotNull($this->translation->getPublishedAt());
        $this->assertSame(DateTime::class, get_class($this->translation->getPublishedAt()));
        $this->assertSame($this->translation, $this->translation->setPublished(false));
        $this->assertNull($this->translation->getPublishedAt());
    }

}
