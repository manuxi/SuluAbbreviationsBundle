<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Content\Type;

use Manuxi\SuluAbbreviationsBundle\Content\Type\AbbreviationsSelection;
use Manuxi\SuluAbbreviationsBundle\Content\Type\SingleAbbreviationSelection;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Resolver\AbbreviationResolver;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ReferenceBundle\Application\Collector\ReferenceCollectorInterface;
use Sulu\Bundle\ReferenceBundle\Domain\Model\ReferenceInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\Compat\StructureInterface;

class AbbreviationSelectionTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AbbreviationResolver> */
    private ObjectProphecy $resolver;

    /** @var ObjectProphecy<PropertyInterface> */
    private ObjectProphecy $property;

    protected function setUp(): void
    {
        $this->resolver = $this->prophesize(AbbreviationResolver::class);
        $this->property = $this->prophesize(PropertyInterface::class);

        $structure = $this->prophesize(StructureInterface::class);
        $structure->getLanguageCode()->willReturn('de');
        $this->property->getStructure()->willReturn($structure->reveal());
        $this->property->getName()->willReturn('abbreviations');
    }

    public function testMultipleSelectionResolvesIdsInLocale(): void
    {
        $this->property->getValue()->willReturn(['a', 'b']);
        $this->resolver->resolveMany(['a', 'b'], 'de')->willReturn([['name' => 'A'], ['name' => 'B']]);

        $type = new AbbreviationsSelection($this->resolver->reveal());

        $this->assertSame([['name' => 'A'], ['name' => 'B']], $type->getContentData($this->property->reveal()));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideEmptyValues(): iterable
    {
        yield 'null' => [null];
        yield 'empty array' => [[]];
        yield 'string' => ['abc'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideEmptyValues')]
    public function testMultipleSelectionReturnsEmptyArrayForInvalidValue(mixed $value): void
    {
        $this->property->getValue()->willReturn($value);
        $this->resolver->resolveMany(Argument::cetera())->shouldNotBeCalled();

        $type = new AbbreviationsSelection($this->resolver->reveal());

        $this->assertSame([], $type->getContentData($this->property->reveal()));
    }

    public function testMultipleSelectionViewData(): void
    {
        $this->property->getValue()->willReturn(['a']);

        $type = new AbbreviationsSelection($this->resolver->reveal());

        $this->assertSame(['ids' => ['a']], $type->getViewData($this->property->reveal()));
    }

    public function testMultipleSelectionCollectsReferences(): void
    {
        $this->property->getValue()->willReturn(['a', '', 5, 'b']);
        $collector = $this->prophesize(ReferenceCollectorInterface::class);
        $collector->addReference(Argument::cetera())->willReturn($this->prophesize(ReferenceInterface::class)->reveal());

        $type = new AbbreviationsSelection($this->resolver->reveal());
        $type->getReferences($this->property->reveal(), $collector->reveal(), 'block.');

        $collector->addReference(Abbreviation::RESOURCE_KEY, 'a', 'block.abbreviations')->shouldHaveBeenCalledOnce();
        $collector->addReference(Abbreviation::RESOURCE_KEY, 'b', 'block.abbreviations')->shouldHaveBeenCalledOnce();
        $collector->addReference(Argument::cetera())->shouldHaveBeenCalledTimes(2);
    }

    public function testMultipleSelectionCollectsNothingForInvalidValue(): void
    {
        $this->property->getValue()->willReturn(null);
        $collector = $this->prophesize(ReferenceCollectorInterface::class);

        $type = new AbbreviationsSelection($this->resolver->reveal());
        $type->getReferences($this->property->reveal(), $collector->reveal());

        $collector->addReference(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testSingleSelectionResolvesIdInLocale(): void
    {
        $this->property->getValue()->willReturn('a');
        $this->resolver->resolve('a', 'de')->willReturn(['name' => 'A']);

        $type = new SingleAbbreviationSelection($this->resolver->reveal());

        $this->assertSame(['name' => 'A'], $type->getContentData($this->property->reveal()));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideEmptySingleValues(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'array' => [['a']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideEmptySingleValues')]
    public function testSingleSelectionReturnsNullForInvalidValue(mixed $value): void
    {
        $this->property->getValue()->willReturn($value);
        $this->resolver->resolve(Argument::cetera())->shouldNotBeCalled();

        $type = new SingleAbbreviationSelection($this->resolver->reveal());

        $this->assertNull($type->getContentData($this->property->reveal()));
    }

    public function testSingleSelectionViewData(): void
    {
        $this->property->getValue()->willReturn('a');

        $type = new SingleAbbreviationSelection($this->resolver->reveal());

        $this->assertSame(['id' => 'a'], $type->getViewData($this->property->reveal()));
    }

    public function testSingleSelectionCollectsReference(): void
    {
        $this->property->getValue()->willReturn('a');
        $collector = $this->prophesize(ReferenceCollectorInterface::class);
        $collector->addReference(Argument::cetera())->willReturn($this->prophesize(ReferenceInterface::class)->reveal());

        $type = new SingleAbbreviationSelection($this->resolver->reveal());
        $type->getReferences($this->property->reveal(), $collector->reveal());

        $collector->addReference(Abbreviation::RESOURCE_KEY, 'a', 'abbreviations')->shouldHaveBeenCalledOnce();
    }

    public function testSingleSelectionCollectsNothingForEmptyValue(): void
    {
        $this->property->getValue()->willReturn(null);
        $collector = $this->prophesize(ReferenceCollectorInterface::class);

        $type = new SingleAbbreviationSelection($this->resolver->reveal());
        $type->getReferences($this->property->reveal(), $collector->reveal());

        $collector->addReference(Argument::cetera())->shouldNotHaveBeenCalled();
    }
}
