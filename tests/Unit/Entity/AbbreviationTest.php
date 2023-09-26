<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Entity;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationExcerpt;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationSeo;
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

    public function testAbbreviationSeo(): void
    {
        $eventSeo = $this->prophesize(AbbreviationSeo::class);
        $eventSeo->getId()->willReturn(42);

        $this->assertInstanceOf(AbbreviationSeo::class, $this->entity->getAbbreviationSeo());
        $this->assertNull($this->entity->getAbbreviationSeo()->getId());
        $this->assertSame($this->entity, $this->entity->setAbbreviationSeo($eventSeo->reveal()));
        $this->assertSame($eventSeo->reveal(), $this->entity->getAbbreviationSeo());
    }

    public function testAbbreviationExcerpt(): void
    {
        $eventExcerpt = $this->prophesize(AbbreviationExcerpt::class);
        $eventExcerpt->getId()->willReturn(42);

        $this->assertInstanceOf(AbbreviationExcerpt::class, $this->entity->getAbbreviationExcerpt());
        $this->assertNull($this->entity->getAbbreviationExcerpt()->getId());
        $this->assertSame($this->entity, $this->entity->setAbbreviationExcerpt($eventExcerpt->reveal()));
        $this->assertSame($eventExcerpt->reveal(), $this->entity->getAbbreviationExcerpt());
    }

    public function testExt(): void
    {
        $ext = $this->entity->getExt();
        $this->assertArrayHasKey('seo', $ext);
        $this->assertInstanceOf(AbbreviationSeo::class, $ext['seo']);
        $this->assertNull($ext['seo']->getId());

        $this->assertArrayHasKey('excerpt', $ext);
        $this->assertInstanceOf(AbbreviationExcerpt::class, $ext['excerpt']);
        $this->assertNull($ext['excerpt']->getId());

        $this->entity->addExt('foo', new AbbreviationSeo());
        $this->entity->addExt('bar', new AbbreviationExcerpt());
        $ext = $this->entity->getExt();

        $this->assertArrayHasKey('seo', $ext);
        $this->assertInstanceOf(AbbreviationSeo::class, $ext['seo']);
        $this->assertNull($ext['seo']->getId());

        $this->assertArrayHasKey('excerpt', $ext);
        $this->assertInstanceOf(AbbreviationExcerpt::class, $ext['excerpt']);
        $this->assertNull($ext['excerpt']->getId());

        $this->assertArrayHasKey('foo', $ext);
        $this->assertInstanceOf(AbbreviationSeo::class, $ext['foo']);
        $this->assertNull($ext['foo']->getId());

        $this->assertArrayHasKey('bar', $ext);
        $this->assertInstanceOf(AbbreviationExcerpt::class, $ext['bar']);
        $this->assertNull($ext['bar']->getId());

        $this->assertTrue($this->entity->hasExt('seo'));
        $this->assertTrue($this->entity->hasExt('excerpt'));
        $this->assertTrue($this->entity->hasExt('foo'));
        $this->assertTrue($this->entity->hasExt('bar'));

        $this->entity->setExt(['and' => 'now', 'something' => 'special']);
        $ext = $this->entity->getExt();
        $this->assertArrayNotHasKey('seo', $ext);
        $this->assertArrayNotHasKey('excerpt', $ext);
        $this->assertArrayNotHasKey('foo', $ext);
        $this->assertArrayNotHasKey('bar', $ext);
        $this->assertArrayHasKey('and', $ext);
        $this->assertArrayHasKey('something', $ext);
        $this->assertTrue($this->entity->hasExt('and'));
        $this->assertTrue($this->entity->hasExt('something'));
        $this->assertTrue('now' === $ext['and']);
        $this->assertTrue('special' === $ext['something']);
    }

    public function testPropagateLocale(): void
    {
        $this->assertSame($this->entity->getExt()['seo']->getLocale(), 'de');
        $this->assertSame($this->entity->getExt()['excerpt']->getLocale(), 'de');
        $this->entity->setLocale('en');
        $this->assertSame($this->entity->getExt()['seo']->getLocale(), 'en');
        $this->assertSame($this->entity->getExt()['excerpt']->getLocale(), 'en');
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
