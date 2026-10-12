<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Entity;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationExcerpt;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationSeo;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationTranslation;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;

class AbbreviationTest extends TestCase
{
    use ProphecyTrait;

    private Abbreviation $entity;
    private string $testString = 'Lorem ipsum dolor sit amet, ...';

    protected function setUp(): void
    {
        $this->entity = new Abbreviation();
        $this->entity->setLocale('de');
    }

    public function testPublished(): void
    {
        $this->assertNull($this->entity->isPublished());
        $this->assertSame($this->entity, $this->entity->setPublished(true));
        $this->assertTrue($this->entity->isPublished());
        $this->assertSame($this->entity, $this->entity->setPublished(false));
        $this->assertFalse($this->entity->isPublished());
    }

    public function testPublishedState(): void
    {
        $this->assertNull($this->entity->getPublishedState());
        $this->assertSame($this->entity, $this->entity->setPublished(true));
        $this->assertSame(1, $this->entity->getPublishedState());
        $this->assertSame($this->entity, $this->entity->setPublished(false));
        $this->assertSame(0, $this->entity->getPublishedState());
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
        $abbreviationSeo = $this->prophesize(AbbreviationSeo::class);
        $abbreviationSeo->getId()->willReturn(42);

        $this->assertInstanceOf(AbbreviationSeo::class, $this->entity->getSeo());
        $this->assertNull($this->entity->getSeo()->getId());
        $this->assertSame($this->entity, $this->entity->setSeo($abbreviationSeo->reveal()));
        $this->assertSame($abbreviationSeo->reveal(), $this->entity->getSeo());
    }

    public function testAbbreviationExcerpt(): void
    {
        $abbreviationExcerpt = $this->prophesize(AbbreviationExcerpt::class);
        $abbreviationExcerpt->getId()->willReturn(42);

        $this->assertInstanceOf(AbbreviationExcerpt::class, $this->entity->getExcerpt());
        $this->assertNull($this->entity->getExcerpt()->getId());
        $this->assertSame($this->entity, $this->entity->setExcerpt($abbreviationExcerpt->reveal()));
        $this->assertSame($abbreviationExcerpt->reveal(), $this->entity->getExcerpt());
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
        // No need to test more, it's s already done...
    }

    public function testCopy(): void
    {
        $category = $this->prophesize(CategoryInterface::class)->reveal();

        $this->entity->setName('API')->setExplanation('Application Programming Interface')->setPublished(true);
        $this->entity->setRoutePath('/abkuerzungen/api');
        $this->entity->getSeo()->setTitle('SEO title');
        $this->entity->getSeo()->setNoIndex(true);
        $this->entity->getExcerpt()->setTitle('Excerpt title');
        $this->entity->getExcerpt()->addCategory($category);

        $this->entity->setLocale('en');
        $this->entity->setName('API (en)')->setPublished(true);

        $copy = $this->entity->copy();

        $this->assertNotSame($this->entity, $copy);
        $this->assertNull($copy->getId());
        $this->assertSame('en', $copy->getLocale());
        $this->assertSame(['de', 'en'], $copy->getAvailableLocales());

        $this->assertSame('API (en)', $copy->getName());
        $this->assertFalse($copy->isPublished());
        $this->assertSame('', $copy->getRoutePath());

        $copy->setLocale('de');
        $this->assertSame('API', $copy->getName());
        $this->assertSame('Application Programming Interface', $copy->getExplanation());
        $this->assertFalse($copy->isPublished());
        $this->assertSame('/abkuerzungen/api-copy', $copy->getRoutePath());
        $this->assertNull($copy->getTranslations()['de']->getId());
        $this->assertSame($copy, $copy->getTranslations()['de']->getAbbreviation());

        $this->assertNotSame($this->entity->getSeo(), $copy->getSeo());
        $this->assertSame($copy, $copy->getSeo()->getAbbreviation());
        $this->assertSame('SEO title', $copy->getSeo()->getTitle());
        $this->assertTrue($copy->getSeo()->getNoIndex());
        $this->assertSame($copy->getSeo(), $copy->getSeo()->getTranslations()['de']->getSeo());

        $this->assertNotSame($this->entity->getExcerpt(), $copy->getExcerpt());
        $this->assertSame($copy, $copy->getExcerpt()->getAbbreviation());
        $this->assertSame('Excerpt title', $copy->getExcerpt()->getTitle());
        $this->assertSame($copy->getExcerpt(), $copy->getExcerpt()->getTranslations()['de']->getExcerpt());

        $originalCategories = $this->entity->getExcerpt()->getTranslations()['de']->getCategories();
        $copiedCategories   = $copy->getExcerpt()->getTranslations()['de']->getCategories();
        $this->assertNotSame($originalCategories, $copiedCategories);
        $this->assertSame([$category], $copiedCategories?->toArray());

        $this->entity->setLocale('de');
        $this->assertSame('/abkuerzungen/api', $this->entity->getRoutePath());
        $this->assertTrue($this->entity->isPublished());
    }
}
