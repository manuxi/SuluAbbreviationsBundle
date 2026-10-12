<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Content\Type;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Manuxi\SuluAbbreviationsBundle\Content\Type\AbbreviationsSelection;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Tests\Unit\Traits\AbbreviationFactoryTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\StructureInterface;

class AbbreviationSelectionTest extends TestCase
{
    use AbbreviationFactoryTrait;
    use ProphecyTrait;

    private AbbreviationsSelection $abbreviationSelection;

    /**
     * @var ObjectProphecy<ObjectRepository<Abbreviation>>
     */
    private ObjectProphecy $abbreviationRepository;

    protected function setUp(): void
    {
        $this->abbreviationRepository = $this->prophesize(ObjectRepository::class);
        $entityManager                = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getRepository(Abbreviation::class)->willReturn($this->abbreviationRepository->reveal());

        $this->abbreviationSelection = new AbbreviationsSelection($entityManager->reveal());
    }

    public function testNullValue(): void
    {
        $property = $this->createProperty(null);

        $this->assertSame([], $this->abbreviationSelection->getContentData($property));
        $this->assertSame(['ids' => null], $this->abbreviationSelection->getViewData($property));
    }

    public function testEmptyArrayValue(): void
    {
        $property = $this->createProperty([]);

        $this->assertSame([], $this->abbreviationSelection->getContentData($property));
        $this->assertSame(['ids' => []], $this->abbreviationSelection->getViewData($property));
    }

    public function testValidValueKeepsSelectionOrderAndSetsLocale(): void
    {
        $property = $this->createProperty([45, 22], 'de');

        $abbreviation22 = $this->createAbbreviation(22, 'de');
        $abbreviation45 = $this->createAbbreviation(45, 'de');
        $abbreviation22->setLocale('en');
        $abbreviation45->setLocale('en');

        $this->abbreviationRepository->findBy(['id' => [45, 22]])->willReturn([$abbreviation22, $abbreviation45]);

        $result = $this->abbreviationSelection->getContentData($property);

        $this->assertSame([$abbreviation45, $abbreviation22], $result);
        $this->assertSame('de', $result[0]->getLocale());
        $this->assertSame('de', $result[1]->getLocale());
        $this->assertSame(['ids' => [45, 22]], $this->abbreviationSelection->getViewData($property));
    }

    public function testStringIdsAreSupported(): void
    {
        $property       = $this->createProperty(['22']);
        $abbreviation22 = $this->createAbbreviation(22);

        $this->abbreviationRepository->findBy(['id' => [22]])->willReturn([$abbreviation22]);

        $this->assertSame([$abbreviation22], $this->abbreviationSelection->getContentData($property));
    }

    public function testUnpublishedAbbreviationsAreSkipped(): void
    {
        $property = $this->createProperty([1, 2, 3], 'en');

        $published   = $this->createAbbreviation(1, 'en', true);
        $unpublished = $this->createAbbreviation(2, 'en', false);
        $otherLocale = $this->createAbbreviation(3, 'de', true);

        $this->abbreviationRepository->findBy(['id' => [1, 2, 3]])->willReturn([$published, $unpublished, $otherLocale]);

        $this->assertSame([$published], $this->abbreviationSelection->getContentData($property));
    }

    private function createProperty(mixed $value, string $locale = 'en'): PropertyInterface
    {
        $structure = $this->prophesize(StructureInterface::class);
        $structure->getLanguageCode()->willReturn($locale);

        $property = $this->prophesize(PropertyInterface::class);
        $property->getValue()->willReturn($value);
        $property->getStructure()->willReturn($structure->reveal());

        return $property->reveal();
    }
}
