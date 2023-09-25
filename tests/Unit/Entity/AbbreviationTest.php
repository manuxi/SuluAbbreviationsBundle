<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Entity;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\NewsExcerpt;
use Manuxi\SuluAbbreviationsBundle\Entity\NewsSeo;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationTranslation;
use Manuxi\SuluAbbreviationsBundle\Entity\Location;
use DateTimeImmutable;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class AbbreviationTest extends SuluTestCase
{
    private Abbreviation $entity;
    private string $testString = "Lorem ipsum dolor sit amet, ...";

    protected function setUp(): void
    {
        $this->entity = new Abbreviation();
        $this->entity->setLocale('de');
    }

    public function testName(): void
    {
        $this->assertNull($this->entity->getName());
        $this->assertSame($this->entity, $this->entity->setName($this->testString));
        $this->assertSame($this->testString, $this->entity->getName());

        $this->assertInstanceOf(AbbreviationTranslation::class, $this->entity->getTranslations()['de']);
        $this->assertSame('de', $this->entity->getTranslations()['de']->getLocale());
        $this->assertSame($this->testString, $this->entity->getTranslations()['de']->getName());
    }

    public function testExplanation(): void
    {
        $this->assertNull($this->entity->getExplanation());
        $this->assertSame($this->entity, $this->entity->setExplanation($this->testString));
        $this->assertSame($this->testString, $this->entity->getExplanation());

        $this->assertInstanceOf(AbbreviationTranslation::class, $this->entity->getTranslations()['de']);
        $this->assertSame('de', $this->entity->getTranslations()['de']->getLocale());
        $this->assertSame($this->testString, $this->entity->getTranslations()['de']->getExplanation());
    }

    public function testDescription(): void
    {
        $this->assertNull($this->entity->getDescription());
        $this->assertSame($this->entity, $this->entity->setDescription($this->testString));
        $this->assertSame($this->testString, $this->entity->getDescription());

        $this->assertInstanceOf(AbbreviationTranslation::class, $this->entity->getTranslations()['de']);
        $this->assertSame('de', $this->entity->getTranslations()['de']->getLocale());
        $this->assertSame($this->testString, $this->entity->getTranslations()['de']->getDescription());
    }

    public function testLocale(): void
    {
        $this->assertSame('de', $this->entity->getLocale());
        $this->assertSame($this->entity, $this->entity->setLocale('en'));
        $this->assertSame('en', $this->entity->getLocale());
    }

    public function testTranslations(): void
    {
        $this->assertSame($this->entity->getTranslations(), []);
        $this->entity->setDescription($this->testString);
        $this->assertNotSame($this->entity->getTranslations(), []);
        $this->assertArrayHasKey('de', $this->entity->getTranslations());
        $this->assertArrayNotHasKey('en', $this->entity->getTranslations());
        $this->assertSame($this->entity->getDescription(), $this->testString);

        $this->entity->setLocale('en');
        $this->entity->setDescription($this->testString);
        $this->assertArrayHasKey('de', $this->entity->getTranslations());
        $this->assertArrayHasKey('en', $this->entity->getTranslations());
        $this->assertSame($this->entity->getDescription(), $this->testString);
        //No need to test more, it's s already done...
    }
}
