<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Content\Type;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Manuxi\SuluAbbreviationsBundle\Content\Type\SingleAbbreviationSelection;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Tests\Unit\Traits\AbbreviationFactoryTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\StructureInterface;

class SingleAbbreviationSelectionTest extends TestCase
{
    use AbbreviationFactoryTrait;
    use ProphecyTrait;

    private SingleAbbreviationSelection $singleAbbreviationSelection;

    /**
     * @var ObjectProphecy<ObjectRepository<Abbreviation>>
     */
    private ObjectProphecy $abbreviationRepository;

    protected function setUp(): void
    {
        $this->abbreviationRepository = $this->prophesize(ObjectRepository::class);
        $entityManager                = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getRepository(Abbreviation::class)->willReturn($this->abbreviationRepository->reveal());

        $this->singleAbbreviationSelection = new SingleAbbreviationSelection($entityManager->reveal());
    }

    public function testNullValue(): void
    {
        $property = $this->createProperty(null);

        $this->assertNull($this->singleAbbreviationSelection->getContentData($property));
        $this->assertSame(['id' => null], $this->singleAbbreviationSelection->getViewData($property));
    }

    public function testValidValueSetsLocale(): void
    {
        $property       = $this->createProperty(45, 'de');
        $abbreviation45 = $this->createAbbreviation(45, 'de');
        $abbreviation45->setLocale('en');

        $this->abbreviationRepository->find(45)->willReturn($abbreviation45);

        $this->assertSame($abbreviation45, $this->singleAbbreviationSelection->getContentData($property));
        $this->assertSame('de', $abbreviation45->getLocale());
        $this->assertSame(['id' => 45], $this->singleAbbreviationSelection->getViewData($property));
    }

    public function testUnpublishedAbbreviationReturnsNull(): void
    {
        $property = $this->createProperty(45);

        $this->abbreviationRepository->find(45)->willReturn($this->createAbbreviation(45, 'en', false));

        $this->assertNull($this->singleAbbreviationSelection->getContentData($property));
    }

    public function testMissingAbbreviationReturnsNull(): void
    {
        $property = $this->createProperty(45);

        $this->abbreviationRepository->find(45)->willReturn(null);

        $this->assertNull($this->singleAbbreviationSelection->getContentData($property));
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
