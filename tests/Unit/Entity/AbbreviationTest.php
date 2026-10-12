<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Entity;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use PHPUnit\Framework\TestCase;

class AbbreviationTest extends TestCase
{
    public function testConstructorGeneratesUuid(): void
    {
        $first  = new Abbreviation();
        $second = new Abbreviation();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $first->getId());
        $this->assertNotSame($first->getId(), $second->getId());
    }

    public function testConstructorAcceptsExplicitId(): void
    {
        $this->assertSame('0195a1f0-0000-7000-8000-000000000001', (new Abbreviation('0195a1f0-0000-7000-8000-000000000001'))->getId());
    }

    public function testLegacyId(): void
    {
        $abbreviation = new Abbreviation();
        $this->assertNull($abbreviation->getLegacyId());

        $abbreviation->setLegacyId(42);
        $this->assertSame(42, $abbreviation->getLegacyId());
    }

    public function testCreateDimensionContent(): void
    {
        $abbreviation     = new Abbreviation();
        $dimensionContent = $abbreviation->createDimensionContent();

        $this->assertInstanceOf(AbbreviationDimensionContent::class, $dimensionContent);
        $this->assertSame($abbreviation, $dimensionContent->getResource());
    }

    public function testDimensionContentsCanBeAddedAndRemoved(): void
    {
        $abbreviation     = new Abbreviation();
        $dimensionContent = $abbreviation->createDimensionContent();

        $this->assertCount(0, $abbreviation->getDimensionContents());

        $abbreviation->addDimensionContent($dimensionContent);
        $this->assertCount(1, $abbreviation->getDimensionContents());

        $abbreviation->removeDimensionContent($dimensionContent);
        $this->assertCount(0, $abbreviation->getDimensionContents());
    }

    public function testConstants(): void
    {
        $this->assertSame('abbreviations', Abbreviation::RESOURCE_KEY);
        $this->assertSame('abbreviation', Abbreviation::TEMPLATE_TYPE);
        $this->assertSame('abbreviation_default', Abbreviation::DEFAULT_TEMPLATE);
        $this->assertSame('sulu.abbreviations.abbreviations', Abbreviation::SECURITY_CONTEXT);
    }
}
