<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Automation;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Automation\AbbreviationUnpublishTaskHandler;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationUnpublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Tests\Unit\Traits\AbbreviationFactoryTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AbbreviationUnpublishTaskHandlerTest extends TestCase
{
    use AbbreviationFactoryTrait;
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<AbbreviationRepository>
     */
    private ObjectProphecy $repository;

    /**
     * @var ObjectProphecy<DomainEventCollectorInterface>
     */
    private ObjectProphecy $domainEventCollector;

    private AbbreviationUnpublishTaskHandler $handler;

    protected function setUp(): void
    {
        $this->repository           = $this->prophesize(AbbreviationRepository::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getRepository(Abbreviation::class)->willReturn($this->repository->reveal());

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans('sulu_abbreviations.unpublish', [], 'admin')->willReturn('translated-unpublish');

        $dispatcher = $this->prophesize(EventDispatcherInterface::class);
        $dispatcher->dispatch(Argument::any())->willReturnArgument(0);

        $this->handler = new AbbreviationUnpublishTaskHandler(
            $entityManager->reveal(),
            $translator->reveal(),
            $this->domainEventCollector->reveal(),
            $dispatcher->reveal()
        );
    }

    public function testConfigurationUsesExistingTranslationKey(): void
    {
        $this->assertSame('translated-unpublish', $this->handler->getConfiguration()->getTitle());
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->handler->supports(Abbreviation::class));
        $this->assertFalse($this->handler->supports(\stdClass::class));
    }

    public function testOptionsResolverRequiresIdAndLocale(): void
    {
        $resolver = $this->handler->configureOptionsResolver(new OptionsResolver());

        $this->assertSame(['id', 'locale'], $resolver->getRequiredOptions());
    }

    public function testHandle(): void
    {
        $abbreviation = $this->createAbbreviation(5, 'de', true);

        $this->repository->findById(5, 'de')->willReturn($abbreviation);
        $this->repository->save($abbreviation)->shouldBeCalledOnce()->willReturn($abbreviation);
        $this->domainEventCollector->collect(Argument::type(AbbreviationUnpublishedEvent::class))->shouldBeCalledOnce();

        $this->handler->handle(['class' => Abbreviation::class, 'id' => '5', 'locale' => 'de']);

        $this->assertFalse($abbreviation->isPublished());
    }

    public function testHandleIgnoresMissingAbbreviation(): void
    {
        $this->repository->findById(5, 'de')->willReturn(null);
        $this->repository->save(Argument::any())->shouldNotBeCalled();

        $this->handler->handle(['class' => Abbreviation::class, 'id' => '5', 'locale' => 'de']);
    }
}
