<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Entity;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use PHPUnit\Framework\TestCase;

class AbbreviationDimensionContentTest extends TestCase
{
    public function testNameIsExtractedFromTemplateData(): void
    {
        $dimensionContent = new AbbreviationDimensionContent(new Abbreviation());
        $dimensionContent->setTemplateData(['name' => 'HTML', 'explanation' => 'HyperText Markup Language']);

        $this->assertSame('HTML', $dimensionContent->getName());
        $this->assertSame('HTML', $dimensionContent->getTitle());
        $this->assertSame('HyperText Markup Language', $dimensionContent->getTemplateData()['explanation']);
    }

    public function testNameIsKeptWhenTemplateDataHasNoName(): void
    {
        $dimensionContent = new AbbreviationDimensionContent(new Abbreviation());
        $dimensionContent->setTemplateData(['name' => 'CSS']);
        $dimensionContent->setTemplateData(['explanation' => 'Cascading Style Sheets']);

        $this->assertSame('CSS', $dimensionContent->getName());
    }

    public function testNonStringNameIsIgnored(): void
    {
        $dimensionContent = new AbbreviationDimensionContent(new Abbreviation());
        $dimensionContent->setTemplateData(['name' => ['invalid']]);

        $this->assertNull($dimensionContent->getName());
    }

    public function testResourceInformation(): void
    {
        $abbreviation     = new Abbreviation();
        $dimensionContent = new AbbreviationDimensionContent($abbreviation);

        $this->assertSame($abbreviation, $dimensionContent->getResource());
        $this->assertSame($abbreviation->getId(), $dimensionContent->getResourceId());
        $this->assertSame('abbreviations', AbbreviationDimensionContent::getResourceKey());
        $this->assertSame('abbreviation', AbbreviationDimensionContent::getTemplateType());
        $this->assertNull($dimensionContent->getId());
    }
}
