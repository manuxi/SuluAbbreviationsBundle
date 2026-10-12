<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Link;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Link\AbbreviationLinkProvider;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Tests\Unit\Traits\AbbreviationFactoryTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Contracts\Translation\TranslatorInterface;

class AbbreviationLinkProviderTest extends TestCase
{
    use AbbreviationFactoryTrait;
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<AbbreviationRepository>
     */
    private ObjectProphecy $repository;

    private AbbreviationLinkProvider $provider;

    protected function setUp(): void
    {
        $this->repository = $this->prophesize(AbbreviationRepository::class);

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans(Argument::type('string'), [], 'admin')->willReturnArgument(0);

        $this->provider = new AbbreviationLinkProvider($this->repository->reveal(), $translator->reveal());
    }

    public function testConfiguration(): void
    {
        $configuration = $this->provider->getConfiguration();

        // LinkConfiguration has no getters, it is only serialized for the admin
        $this->assertSame(Abbreviation::RESOURCE_KEY, (new \ReflectionProperty($configuration, 'resourceKey'))->getValue($configuration));
        $this->assertSame('sulu_abbreviations.abbreviation', (new \ReflectionProperty($configuration, 'title'))->getValue($configuration));
    }

    public function testPreloadWithoutHrefs(): void
    {
        $this->repository->findBy(Argument::any())->shouldNotBeCalled();

        $this->assertSame([], $this->provider->preload([], 'en'));
    }

    public function testPreloadReturnsLinkItemsInLocale(): void
    {
        $published = $this->createAbbreviation(1, 'de', true, 'API');
        $published->setRoutePath('/abkuerzungen/api');
        $published->setLocale('en');
        $otherLocale = $this->createAbbreviation(2, 'en', false, 'CMS');

        $this->repository->findBy(['id' => ['1', '2']])->willReturn([$published, $otherLocale]);

        $items = $this->provider->preload(['1', '2'], 'de');

        $this->assertCount(2, $items);
        $this->assertSame('1', $items[0]->getId());
        $this->assertSame('API', $items[0]->getTitle());
        $this->assertSame('/abkuerzungen/api', $items[0]->getUrl());
        $this->assertTrue($items[0]->isPublished());
        $this->assertSame('2', $items[1]->getId());
        $this->assertFalse($items[1]->isPublished());
    }
}
