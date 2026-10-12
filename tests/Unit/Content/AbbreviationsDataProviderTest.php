<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Content;

use Manuxi\SuluAbbreviationsBundle\Content\AbbreviationsDataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Serializer\ArraySerializerInterface;
use Sulu\Component\SmartContent\Orm\DataProviderRepositoryInterface;

class AbbreviationsDataProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testResourceItemsMarkTheResponseAsList(): void
    {
        $referenceStore = $this->prophesize(ReferenceStoreInterface::class);
        $referenceStore->add('list')->shouldBeCalledOnce();

        $provider = new AbbreviationsDataProvider(
            $this->prophesize(DataProviderRepositoryInterface::class)->reveal(),
            $this->prophesize(ArraySerializerInterface::class)->reveal(),
            $this->prophesize(ContentManagerInterface::class)->reveal(),
            $referenceStore->reveal()
        );

        $method = new \ReflectionMethod($provider, 'decorateResourceItems');

        $this->assertSame([], $method->invoke($provider, [], 'en'));
    }

    public function testWorksWithoutReferenceStore(): void
    {
        $provider = new AbbreviationsDataProvider(
            $this->prophesize(DataProviderRepositoryInterface::class)->reveal(),
            $this->prophesize(ArraySerializerInterface::class)->reveal(),
            $this->prophesize(ContentManagerInterface::class)->reveal()
        );

        $method = new \ReflectionMethod($provider, 'decorateResourceItems');

        $this->assertSame([], $method->invoke($provider, [], 'en'));
    }
}
