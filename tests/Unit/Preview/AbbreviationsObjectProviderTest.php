<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Preview;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Mapper\AbbreviationDataMapper;
use Manuxi\SuluAbbreviationsBundle\Preview\AbbreviationsObjectProvider;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Tests\Unit\Traits\AbbreviationFactoryTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ContactBundle\Entity\ContactRepository;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;

class AbbreviationsObjectProviderTest extends TestCase
{
    use AbbreviationFactoryTrait;
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<AbbreviationRepository>
     */
    private ObjectProphecy $repository;

    private AbbreviationsObjectProvider $provider;

    protected function setUp(): void
    {
        $this->repository = $this->prophesize(AbbreviationRepository::class);

        $this->provider = new AbbreviationsObjectProvider(
            $this->repository->reveal(),
            new AbbreviationDataMapper(
                $this->prophesize(MediaRepositoryInterface::class)->reveal(),
                $this->prophesize(ContactRepository::class)->reveal()
            )
        );
    }

    public function testGetObjectAndId(): void
    {
        $abbreviation = $this->createAbbreviation(12, 'de');
        $this->repository->findById(12, 'de')->willReturn($abbreviation);

        $this->assertSame($abbreviation, $this->provider->getObject('12', 'de'));
        $this->assertSame('12', $this->provider->getId($abbreviation));
    }

    public function testSecurityContextDoesNotDependOnObject(): void
    {
        $this->repository->findById()->shouldNotBeCalled();

        $this->assertSame(Abbreviation::SECURITY_CONTEXT, $this->provider->getSecurityContext('12', 'de'));
    }

    public function testSetValuesMapsFormDataInGivenLocale(): void
    {
        $abbreviation = $this->createAbbreviation(12, 'de', true, 'Alt');

        $this->provider->setValues($abbreviation, 'en', [
            'name'        => 'API',
            'explanation' => 'Application Programming Interface',
        ]);

        $this->assertSame('en', $abbreviation->getLocale());
        $this->assertSame('API', $abbreviation->getName());
        $this->assertSame('Application Programming Interface', $abbreviation->getExplanation());

        $abbreviation->setLocale('de');
        $this->assertSame('Alt', $abbreviation->getName());
    }

    public function testSetContextReturnsObject(): void
    {
        $abbreviation = $this->createAbbreviation(12);

        $this->assertSame($abbreviation, $this->provider->setContext($abbreviation, 'en', ['template' => 'default']));
    }

    public function testSerializeRoundTrip(): void
    {
        $abbreviation = $this->createAbbreviation(12, 'en', true, 'API');
        $abbreviation->setExplanation('Application Programming Interface');

        $restored = $this->provider->deserialize($this->provider->serialize($abbreviation), Abbreviation::class);

        $this->assertSame(12, $restored->getId());
        $this->assertSame('API', $restored->getName());
        $this->assertSame('Application Programming Interface', $restored->getExplanation());
    }
}
