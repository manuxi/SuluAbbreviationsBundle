<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Mapper;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Mapper\AbbreviationDataMapper;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ContactBundle\Entity\ContactInterface;
use Sulu\Bundle\ContactBundle\Entity\ContactRepository;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Component\Rest\Exception\EntityNotFoundException;

class AbbreviationDataMapperTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<MediaRepositoryInterface>
     */
    private ObjectProphecy $mediaRepository;

    /**
     * @var ObjectProphecy<ContactRepository>
     */
    private ObjectProphecy $contactRepository;

    private AbbreviationDataMapper $mapper;

    protected function setUp(): void
    {
        $this->mediaRepository   = $this->prophesize(MediaRepositoryInterface::class);
        $this->contactRepository = $this->prophesize(ContactRepository::class);

        $this->mapper = new AbbreviationDataMapper($this->mediaRepository->reveal(), $this->contactRepository->reveal());
    }

    public function testMapData(): void
    {
        $image = $this->prophesize(MediaInterface::class)->reveal();
        $this->mediaRepository->findMediaById(7)->willReturn($image);

        $entity = (new Abbreviation())->setLocale('de');

        $this->mapper->mapData($entity, [
            'name'        => 'API',
            'explanation' => 'Application Programming Interface',
            'description' => '<p>Text</p>',
            'routePath'   => '/abkuerzungen/api',
            'published'   => true,
            'showAuthor'  => true,
            'showDate'    => false,
            'link'        => ['href' => 'https://example.org'],
            'image'       => ['id' => 7],
        ]);

        $this->assertSame('API', $entity->getName());
        $this->assertSame('Application Programming Interface', $entity->getExplanation());
        $this->assertSame('<p>Text</p>', $entity->getDescription());
        $this->assertSame('/abkuerzungen/api', $entity->getRoutePath());
        $this->assertTrue($entity->isPublished());
        $this->assertTrue($entity->getShowAuthor());
        $this->assertFalse($entity->getShowDate());
        $this->assertSame(['href' => 'https://example.org'], $entity->getLink());
        $this->assertSame($image, $entity->getImage());
        $this->assertSame(['de'], $entity->getAvailableLocales());
    }

    public function testMapDataResetsOptionalFields(): void
    {
        $entity = (new Abbreviation())->setLocale('en');
        $entity->setName('Old');
        $entity->setExplanation('Old explanation');

        $this->mapper->mapData($entity, ['name' => '', 'explanation' => '']);

        $this->assertSame('Old', $entity->getName());
        $this->assertNull($entity->getExplanation());
        $this->assertNull($entity->getImage());
        $this->assertNull($entity->getLink());
    }

    public function testMapDataWithUnknownImageThrows(): void
    {
        $this->mediaRepository->findMediaById(99)->willReturn(null);

        $this->expectException(EntityNotFoundException::class);

        $this->mapper->mapData((new Abbreviation())->setLocale('en'), ['image' => ['id' => 99]]);
    }

    public function testMapSettings(): void
    {
        $author = $this->prophesize(ContactInterface::class);
        $author->getId()->willReturn(3);
        $this->contactRepository->findById(3)->willReturn($author->reveal());

        $entity = (new Abbreviation())->setLocale('en');
        $this->mapper->mapSettings($entity, ['author' => 3, 'authored' => '2026-01-02T03:04:05']);

        $this->assertSame(3, $entity->getAuthor());
        $this->assertSame('2026-01-02 03:04:05', $entity->getAuthored()?->format('Y-m-d H:i:s'));

        $this->mapper->mapSettings($entity, []);

        $this->assertNull($entity->getAuthor());
        $this->assertNull($entity->getAuthored());
    }
}
