<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Controller\Admin;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Manuxi\SuluAbbreviationsBundle\Common\DoctrineListRepresentationFactory;
use Manuxi\SuluAbbreviationsBundle\Controller\Admin\AbbreviationsController;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\Models\AbbreviationExcerptModel;
use Manuxi\SuluAbbreviationsBundle\Entity\Models\AbbreviationModel;
use Manuxi\SuluAbbreviationsBundle\Entity\Models\AbbreviationSeoModel;
use Manuxi\SuluAbbreviationsBundle\Tests\Unit\Traits\AbbreviationFactoryTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AbbreviationsControllerTest extends TestCase
{
    use AbbreviationFactoryTrait;
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<AbbreviationModel>
     */
    private ObjectProphecy $model;

    /**
     * @var ObjectProphecy<SecurityCheckerInterface>
     */
    private ObjectProphecy $securityChecker;

    /**
     * @var \ArrayObject<int, View>
     */
    private \ArrayObject $handledViews;

    private AbbreviationsController $controller;

    protected function setUp(): void
    {
        $this->model           = $this->prophesize(AbbreviationModel::class);
        $this->securityChecker = $this->prophesize(SecurityCheckerInterface::class);

        $viewHandler        = $this->prophesize(ViewHandlerInterface::class);
        $this->handledViews = $handledViews = new \ArrayObject();
        $viewHandler->handle(Argument::type(View::class))->will(static function (array $args) use ($handledViews): Response {
            $handledViews->append($args[0]);

            return new Response('', $args[0]->getStatusCode() ?? 200);
        });

        $this->controller = new AbbreviationsController(
            $this->model->reveal(),
            $this->prophesize(AbbreviationSeoModel::class)->reveal(),
            $this->prophesize(AbbreviationExcerptModel::class)->reveal(),
            $this->prophesize(DoctrineListRepresentationFactory::class)->reveal(),
            $this->securityChecker->reveal(),
            $this->prophesize(TrashManagerInterface::class)->reveal(),
            $viewHandler->reveal()
        );
    }

    public function testPostWithCopyActionCopiesAbbreviation(): void
    {
        $copy    = $this->createAbbreviation(6);
        $request = new Request(['action' => 'copy', 'id' => '5', 'locale' => 'en']);

        $this->model->copy(5, $request)->shouldBeCalledOnce()->willReturn($copy);
        $this->model->create(Argument::any())->shouldNotBeCalled();

        $response = $this->controller->postAction($request);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame($copy, $this->getLastView()->getData());
    }

    public function testPostTriggerCopyAction(): void
    {
        $copy    = $this->createAbbreviation(6);
        $request = new Request(['action' => 'copy', 'locale' => 'en']);

        $this->model->copy(5, $request)->shouldBeCalledOnce()->willReturn($copy);

        $this->controller->postTriggerAction(5, $request);

        $this->assertSame($copy, $this->getLastView()->getData());
    }

    public function testPostWithoutActionCreatesAbbreviation(): void
    {
        $entity  = $this->createAbbreviation(7);
        $request = new Request(['locale' => 'en']);

        $this->model->create($request)->shouldBeCalledOnce()->willReturn($entity);

        $response = $this->controller->postAction($request);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testPublishRequiresLivePermission(): void
    {
        $entity  = $this->createAbbreviation(5);
        $request = new Request(['action' => 'publish', 'locale' => 'de']);

        $this->securityChecker->checkPermission(
            Argument::that(static fn (SecurityCondition $condition): bool => Abbreviation::SECURITY_CONTEXT === $condition->getSecurityContext() && 'de' === $condition->getLocale()),
            PermissionTypes::LIVE
        )->shouldBeCalledOnce()->willReturn(true);
        $this->model->publish(5, $request)->shouldBeCalledOnce()->willReturn($entity);

        $this->controller->postTriggerAction(5, $request);
    }

    public function testUnpublishWithoutLivePermissionIsDenied(): void
    {
        $request = new Request(['action' => 'unpublish', 'locale' => 'de']);

        $this->securityChecker->checkPermission(Argument::type(SecurityCondition::class), PermissionTypes::LIVE)
            ->willThrow(new AccessDeniedException());
        $this->model->unpublish(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(AccessDeniedException::class);

        $this->controller->postTriggerAction(5, $request);
    }

    private function getLastView(): View
    {
        $views = $this->handledViews->getArrayCopy();

        return end($views) ?: throw new \LogicException('No view handled.');
    }
}
