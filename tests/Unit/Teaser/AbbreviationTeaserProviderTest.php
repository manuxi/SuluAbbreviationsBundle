<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Teaser;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Teaser\AbbreviationTeaserProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Application\ContentMetadataInspector\ContentMetadataInspectorInterface;
use Sulu\Bundle\PageBundle\Teaser\Configuration\TeaserConfiguration;
use Sulu\Component\Content\Metadata\Factory\StructureMetadataFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AbbreviationTeaserProviderTest extends TestCase
{
    use ProphecyTrait;

    private function createProvider(): AbbreviationTeaserProvider
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans('sulu_abbreviations.abbreviations', [], 'admin')->willReturn('Abbreviations');
        $translator->trans('sulu_abbreviations.select_abbreviationlist', [], 'admin')->willReturn('Select abbreviation');

        $inspector = $this->prophesize(ContentMetadataInspectorInterface::class);
        $inspector->getDimensionContentClass(Abbreviation::class)->willReturn(AbbreviationDimensionContent::class);

        return new AbbreviationTeaserProvider(
            $this->prophesize(ContentManagerInterface::class)->reveal(),
            $this->prophesize(EntityManagerInterface::class)->reveal(),
            $inspector->reveal(),
            $this->prophesize(StructureMetadataFactoryInterface::class)->reveal(),
            $translator->reveal(),
            false
        );
    }

    public function testConfiguration(): void
    {
        $configuration = $this->createProvider()->getConfiguration();

        $this->assertSame('Abbreviations', $this->read($configuration, 'title'));
        $this->assertSame(Abbreviation::RESOURCE_KEY, $this->read($configuration, 'resourceKey'));
        $this->assertSame('table', $this->read($configuration, 'listAdapter'));
        $this->assertSame(['name'], $this->read($configuration, 'displayProperties'));
        $this->assertSame('Select abbreviation', $this->read($configuration, 'overlayTitle'));
    }

    private function read(TeaserConfiguration $configuration, string $property): mixed
    {
        return (new \ReflectionProperty(TeaserConfiguration::class, $property))->getValue($configuration);
    }

    public function testDescriptionPrefersExcerptDescription(): void
    {
        $content = new AbbreviationDimensionContent(new Abbreviation());
        $content->setExcerptDescription('Excerpt');

        $this->assertSame('Excerpt', $this->callDescription($content, ['explanation' => 'Long form']));
    }

    public function testDescriptionFallsBackToPlainExplanation(): void
    {
        $content = new AbbreviationDimensionContent(new Abbreviation());

        $this->assertSame('Long form', $this->callDescription($content, ['explanation' => '<b>Long</b> form ']));
    }

    public function testDescriptionFallsBackToParentWithoutExplanation(): void
    {
        $content = new AbbreviationDimensionContent(new Abbreviation());

        $this->assertSame('Fallback', $this->callDescription($content, ['explanation' => '', 'description' => 'Fallback']));
        $this->assertNull($this->callDescription($content, []));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function callDescription(AbbreviationDimensionContent $content, array $data): ?string
    {
        $method = new \ReflectionMethod(AbbreviationTeaserProvider::class, 'getDescription');

        $description = $method->invoke($this->createProvider(), $content, $data);
        \assert(null === $description || \is_string($description));

        return $description;
    }
}
