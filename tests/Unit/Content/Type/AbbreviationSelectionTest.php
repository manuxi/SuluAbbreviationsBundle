<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Content\Type;

use Manuxi\SuluAbbreviationsBundle\Content\Type\AbbreviationsSelection;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Component\Content\Compat\PropertyInterface;

class AbbreviationSelectionTest extends TestCase
{
    private AbbreviationsSelection $abbreviationSelection;
    private ObjectProphecy $abbreviationRepository;

    protected function setUp(): void
    {
        $this->abbreviationRepository = $this->prophesize(ObjectRepository::class);
        $entityManager         = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getRepository(Abbreviation::class)->willReturn($this->abbreviationRepository->reveal());

        $this->abbreviationSelection = new AbbreviationsSelection($entityManager->reveal());
    }

    public function testNullValue(): void
    {
        $property = $this->prophesize(PropertyInterface::class);
        $property->getValue()->willReturn(null);

        $this->assertSame([], $this->abbreviationSelection->getContentData($property->reveal()));
        $this->assertSame(['ids' => null], $this->abbreviationSelection->getViewData($property->reveal()));
    }

    public function testEmptyArrayValue(): void
    {
        $property = $this->prophesize(PropertyInterface::class);
        $property->getValue()->willReturn([]);

        $this->assertSame([], $this->abbreviationSelection->getContentData($property->reveal()));
        $this->assertSame(['ids' => []], $this->abbreviationSelection->getViewData($property->reveal()));
    }

    public function testValidValue(): void
    {
        $property = $this->prophesize(PropertyInterface::class);
        $property->getValue()->willReturn([45, 22]);

        $abbreviation22 = $this->prophesize(Abbreviation::class);
        $abbreviation22->getId()->willReturn(22);

        $abbreviation45 = $this->prophesize(Abbreviation::class);
        $abbreviation45->getId()->willReturn(45);

        $this->abbreviationRepository->findBy(['id' => [45, 22]])->willReturn([
            $abbreviation22->reveal(),
            $abbreviation45->reveal(),
        ]);

        $this->assertSame(
            [
                $abbreviation45->reveal(),
                $abbreviation22->reveal(),
            ],
            $this->abbreviationSelection->getContentData($property->reveal())
        );
        $this->assertSame(['ids' => [45, 22]], $this->abbreviationSelection->getViewData($property->reveal()));
    }
}
