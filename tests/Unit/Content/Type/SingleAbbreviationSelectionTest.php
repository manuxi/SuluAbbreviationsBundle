<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Content\Type;

use Manuxi\SuluAbbreviationsBundle\Content\Type\SingleAbbreviationSelection;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Component\Content\Compat\PropertyInterface;

class SingleAbbreviationSelectionTest extends TestCase
{
    private SingleAbbreviationSelection $singleAbbreviationSelection;

    private ObjectProphecy $abbreviationRepository;

    protected function setUp(): void
    {
        $this->abbreviationRepository = $this->prophesize(ObjectRepository::class);
        $entityManager         = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getRepository(Abbreviation::class)->willReturn($this->abbreviationRepository->reveal());

        $this->singleAbbreviationSelection = new SingleAbbreviationSelection($entityManager->reveal());
    }

    public function testNullValue(): void
    {
        $property = $this->prophesize(PropertyInterface::class);
        $property->getValue()->willReturn(null);

        $this->assertNull($this->singleAbbreviationSelection->getContentData($property->reveal()));
        $this->assertSame(['id' => null], $this->singleAbbreviationSelection->getViewData($property->reveal()));
    }

    public function testValidValue(): void
    {
        $property = $this->prophesize(PropertyInterface::class);
        $property->getValue()->willReturn(45);

        $abbreviation45 = $this->prophesize(Abbreviation::class);

        $this->abbreviationRepository->find(45)->willReturn($abbreviation45->reveal());

        $this->assertSame($abbreviation45->reveal(), $this->singleAbbreviationSelection->getContentData($property->reveal()));
        $this->assertSame(['id' => 45], $this->singleAbbreviationSelection->getViewData($property->reveal()));
    }
}
