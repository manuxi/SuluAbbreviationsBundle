<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Link;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Link\AbbreviationLinkProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkConfiguration;
use Sulu\Component\Content\Metadata\Factory\StructureMetadataFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AbbreviationLinkProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testConfiguration(): void
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans(Argument::type('string'), [], 'admin')->will(static fn (array $args): string => 'translated:' . $args[0]);

        $provider = new AbbreviationLinkProvider(
            $this->prophesize(ContentManagerInterface::class)->reveal(),
            $this->prophesize(StructureMetadataFactoryInterface::class)->reveal(),
            $this->prophesize(EntityManagerInterface::class)->reveal(),
            $translator->reveal()
        );

        $configuration = $provider->getConfiguration();

        $this->assertSame('translated:sulu_abbreviations.abbreviation', $this->read($configuration, 'title'));
        $this->assertSame(Abbreviation::RESOURCE_KEY, $this->read($configuration, 'resourceKey'));
        $this->assertSame('table', $this->read($configuration, 'listAdapter'));
        $this->assertSame(['name'], $this->read($configuration, 'displayProperties'));
        $this->assertSame('translated:sulu_abbreviations.select_abbreviation', $this->read($configuration, 'overlayTitle'));
        $this->assertSame('translated:sulu_abbreviations.no_abbreviation_selected', $this->read($configuration, 'emptyText'));
        $this->assertSame('su-tag-pen', $this->read($configuration, 'icon'));
    }

    private function read(LinkConfiguration $configuration, string $property): mixed
    {
        return (new \ReflectionProperty(LinkConfiguration::class, $property))->getValue($configuration);
    }
}
