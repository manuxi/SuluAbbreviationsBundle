<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Admin;

use Manuxi\SuluAbbreviationsBundle\Admin\AbbreviationsAdmin;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Infrastructure\Sulu\Admin\View\ActivityViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\DropdownToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ListViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AutomationBundle\Admin\View\AutomationViewBuilderFactory;
use Sulu\Bundle\AutomationBundle\Admin\View\AutomationViewBuilderFactoryInterface;
use Sulu\Bundle\ContentBundle\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactoryInterface;
use Sulu\Bundle\ReferenceBundle\Infrastructure\Sulu\Admin\View\ReferenceViewBuilderFactoryInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

class AbbreviationsAdminTest extends TestCase
{
    use ProphecyTrait;

    private ViewBuilderFactory $viewBuilderFactory;

    /** @var ObjectProphecy<ContentViewBuilderFactoryInterface> */
    private ObjectProphecy $contentViewBuilderFactory;

    /** @var ObjectProphecy<SecurityCheckerInterface> */
    private ObjectProphecy $securityChecker;

    /** @var ObjectProphecy<WebspaceManagerInterface> */
    private ObjectProphecy $webspaceManager;

    /** @var ObjectProphecy<ActivityViewBuilderFactoryInterface> */
    private ObjectProphecy $activityViewBuilderFactory;

    /** @var ObjectProphecy<ReferenceViewBuilderFactoryInterface> */
    private ObjectProphecy $referenceViewBuilderFactory;

    protected function setUp(): void
    {
        $this->viewBuilderFactory = new ViewBuilderFactory();

        $this->contentViewBuilderFactory = $this->prophesize(ContentViewBuilderFactoryInterface::class);
        $this->contentViewBuilderFactory->getDefaultToolbarActions(Abbreviation::class)->willReturn([
            'save'   => new ToolbarAction('sulu_admin.save_with_publishing'),
            'type'   => new ToolbarAction('sulu_admin.type'),
            'delete' => new ToolbarAction('sulu_admin.delete'),
            'edit'   => new DropdownToolbarAction('sulu_admin.edit', 'su-pen', [
                new ToolbarAction('sulu_admin.delete_draft'),
                new ToolbarAction('sulu_admin.set_unpublished'),
            ]),
        ]);
        $this->contentViewBuilderFactory
            ->createViews(Abbreviation::class, AbbreviationsAdmin::EDIT_FORM_VIEW, AbbreviationsAdmin::ADD_FORM_VIEW, Abbreviation::SECURITY_CONTEXT, Argument::type('array'))
            ->willReturn([]);

        $this->securityChecker = $this->prophesize(SecurityCheckerInterface::class);
        $this->grant(PermissionTypes::VIEW, PermissionTypes::ADD, PermissionTypes::EDIT, PermissionTypes::DELETE, PermissionTypes::LIVE);

        $this->webspaceManager = $this->prophesize(WebspaceManagerInterface::class);
        $this->webspaceManager->getAllLocales()->willReturn(['en', 'de']);

        $this->activityViewBuilderFactory = $this->prophesize(ActivityViewBuilderFactoryInterface::class);
        $this->activityViewBuilderFactory->hasActivityListPermission()->willReturn(true);
        $factory = $this->viewBuilderFactory;
        $this->activityViewBuilderFactory->createActivityListViewBuilder(Argument::cetera())->will(
            static fn (array $args): ListViewBuilderInterface => $factory->createListViewBuilder($args[0], $args[1])->setResourceKey('stub')->setListKey('stub')->addListAdapters(['table'])
        );

        $this->referenceViewBuilderFactory = $this->prophesize(ReferenceViewBuilderFactoryInterface::class);
        $this->referenceViewBuilderFactory->hasReferenceListPermission()->willReturn(true);
        $this->referenceViewBuilderFactory->createReferenceListViewBuilder(Argument::cetera())->will(
            static fn (array $args): ListViewBuilderInterface => $factory->createListViewBuilder($args[0], $args[1])->setResourceKey('stub')->setListKey('stub')->addListAdapters(['table'])
        );
    }

    private function grant(string ...$permissions): void
    {
        foreach ([PermissionTypes::VIEW, PermissionTypes::ADD, PermissionTypes::EDIT, PermissionTypes::DELETE, PermissionTypes::LIVE] as $permission) {
            $this->securityChecker
                ->hasPermission(Abbreviation::SECURITY_CONTEXT, $permission)
                ->willReturn(\in_array($permission, $permissions, true));
        }
    }

    private function createAdmin(?AutomationViewBuilderFactoryInterface $automation = null): AbbreviationsAdmin
    {
        return new AbbreviationsAdmin(
            $this->viewBuilderFactory,
            $this->contentViewBuilderFactory->reveal(),
            $this->securityChecker->reveal(),
            $this->webspaceManager->reveal(),
            $this->activityViewBuilderFactory->reveal(),
            $this->referenceViewBuilderFactory->reveal(),
            $automation
        );
    }

    private function collectViews(AbbreviationsAdmin $admin): ViewCollection
    {
        $collection = new ViewCollection();
        $admin->configureViews($collection);

        return $collection;
    }

    /**
     * @return array<string, ToolbarAction>
     */
    private function captureFormToolbarActions(?AutomationViewBuilderFactoryInterface $automation = null): array
    {
        $captured = null;
        $this->contentViewBuilderFactory
            ->createViews(Abbreviation::class, AbbreviationsAdmin::EDIT_FORM_VIEW, AbbreviationsAdmin::ADD_FORM_VIEW, Abbreviation::SECURITY_CONTEXT, Argument::type('array'))
            ->will(static function (array $arguments) use (&$captured): array {
                $captured = $arguments[4];

                return [];
            });

        $this->collectViews($this->createAdmin($automation));
        \assert(\is_array($captured));

        /* @var array<string, ToolbarAction> $captured */
        return $captured;
    }

    public function testNavigationItemIsAddedWithEditPermission(): void
    {
        $collection = new NavigationItemCollection();
        $this->createAdmin()->configureNavigationItems($collection);

        $item = $collection->get(AbbreviationsAdmin::NAV_ITEM);
        $this->assertSame(AbbreviationsAdmin::LIST_VIEW, $item->getView());
        $this->assertCount(1, $item->getChildren());
    }

    public function testNavigationItemIsMissingWithoutEditPermission(): void
    {
        $this->grant(PermissionTypes::VIEW);

        $collection = new NavigationItemCollection();
        $this->createAdmin()->configureNavigationItems($collection);

        $this->assertFalse($collection->has(AbbreviationsAdmin::NAV_ITEM));
    }

    public function testNoViewsWithoutEditPermission(): void
    {
        $this->grant(PermissionTypes::VIEW);

        $this->assertSame([], $this->collectViews($this->createAdmin())->all());
    }

    public function testViewsAreRegistered(): void
    {
        $collection = $this->collectViews($this->createAdmin());

        foreach ([
            AbbreviationsAdmin::LIST_VIEW,
            AbbreviationsAdmin::ADD_FORM_VIEW,
            AbbreviationsAdmin::EDIT_FORM_VIEW,
            AbbreviationsAdmin::EDIT_INSIGHTS_VIEW,
            AbbreviationsAdmin::EDIT_INSIGHTS_VIEW . '.activity',
            AbbreviationsAdmin::EDIT_INSIGHTS_VIEW . '.references',
        ] as $name) {
            $this->assertTrue($collection->has($name), $name);
        }

        $this->assertFalse($collection->has(AbbreviationsAdmin::EDIT_AUTOMATION_VIEW));
        $this->assertSame(AbbreviationsAdmin::EDIT_INSIGHTS_VIEW, $collection->get(AbbreviationsAdmin::EDIT_INSIGHTS_VIEW . '.activity')->getView()->getParent());
    }

    public function testInsightsViewsAreOmittedWithoutPermissions(): void
    {
        $this->activityViewBuilderFactory->hasActivityListPermission()->willReturn(false);
        $this->referenceViewBuilderFactory->hasReferenceListPermission()->willReturn(false);

        $collection = $this->collectViews($this->createAdmin());

        $this->assertFalse($collection->has(AbbreviationsAdmin::EDIT_INSIGHTS_VIEW));
    }

    public function testOnlyReferencesTabWhenActivityIsNotAllowed(): void
    {
        $this->activityViewBuilderFactory->hasActivityListPermission()->willReturn(false);

        $collection = $this->collectViews($this->createAdmin());

        $this->assertTrue($collection->has(AbbreviationsAdmin::EDIT_INSIGHTS_VIEW));
        $this->assertFalse($collection->has(AbbreviationsAdmin::EDIT_INSIGHTS_VIEW . '.activity'));
        $this->assertTrue($collection->has(AbbreviationsAdmin::EDIT_INSIGHTS_VIEW . '.references'));
    }

    public function testAutomationViewIsAddedWithLivePermission(): void
    {
        $collection = $this->collectViews($this->createAdmin(new AutomationViewBuilderFactory($this->viewBuilderFactory)));

        $this->assertTrue($collection->has(AbbreviationsAdmin::EDIT_AUTOMATION_VIEW));
        $this->assertSame(AbbreviationsAdmin::EDIT_FORM_VIEW, $collection->get(AbbreviationsAdmin::EDIT_AUTOMATION_VIEW)->getView()->getParent());
    }

    public function testAutomationViewIsOmittedWithoutLivePermission(): void
    {
        $this->grant(PermissionTypes::VIEW, PermissionTypes::ADD, PermissionTypes::EDIT, PermissionTypes::DELETE);

        $collection = $this->collectViews($this->createAdmin(new AutomationViewBuilderFactory($this->viewBuilderFactory)));

        $this->assertFalse($collection->has(AbbreviationsAdmin::EDIT_AUTOMATION_VIEW));
    }

    public function testListToolbarActionsFollowPermissions(): void
    {
        $collection = $this->collectViews($this->createAdmin());
        $actions    = $collection->get(AbbreviationsAdmin::LIST_VIEW)->getView()->getOption('toolbarActions');
        \assert(\is_array($actions));

        $this->assertSame(['sulu_admin.add', 'sulu_admin.delete', 'sulu_admin.export'], array_map(static fn (ToolbarAction $action): string => $action->getType(), $actions));

        $this->grant(PermissionTypes::EDIT);
        $collection = $this->collectViews($this->createAdmin());
        $this->assertSame([], $collection->get(AbbreviationsAdmin::LIST_VIEW)->getView()->getOption('toolbarActions'));
    }

    public function testDeleteActionOffersDeletingOneTranslation(): void
    {
        $delete = $this->captureFormToolbarActions()['delete'];

        $this->assertInstanceOf(DropdownToolbarAction::class, $delete);
        /** @var ToolbarAction[] $children */
        $children = $delete->getOptions()['toolbarActions'];
        $this->assertCount(2, $children);
        $this->assertArrayNotHasKey('delete_locale', $children[0]->getOptions());
        $this->assertTrue($children[1]->getOptions()['delete_locale']);
    }

    public function testEditDropdownContainsCopyLocaleForMultipleLocales(): void
    {
        $edit = $this->captureFormToolbarActions()['edit'];

        $this->assertInstanceOf(DropdownToolbarAction::class, $edit);
        /** @var ToolbarAction[] $children */
        $children = $edit->getOptions()['toolbarActions'];
        $this->assertSame(
            ['sulu_admin.copy_locale', 'sulu_admin.delete_draft', 'sulu_admin.set_unpublished'],
            array_map(static fn (ToolbarAction $action): string => $action->getType(), $children)
        );
    }

    public function testEditDropdownOmitsCopyLocaleForSingleLocale(): void
    {
        $this->webspaceManager->getAllLocales()->willReturn(['en']);

        $edit = $this->captureFormToolbarActions()['edit'];

        $this->assertInstanceOf(DropdownToolbarAction::class, $edit);
        /** @var ToolbarAction[] $children */
        $children = $edit->getOptions()['toolbarActions'];
        $this->assertSame(
            ['sulu_admin.delete_draft', 'sulu_admin.set_unpublished'],
            array_map(static fn (ToolbarAction $action): string => $action->getType(), $children)
        );
    }

    public function testDefaultSaveAndTypeActionsAreKept(): void
    {
        $actions = $this->captureFormToolbarActions();

        $this->assertSame('sulu_admin.save_with_publishing', $actions['save']->getType());
        $this->assertSame('sulu_admin.type', $actions['type']->getType());
    }

    public function testSecurityContexts(): void
    {
        $contexts = $this->createAdmin()->getSecurityContexts();

        $this->assertSame(
            [PermissionTypes::VIEW, PermissionTypes::ADD, PermissionTypes::EDIT, PermissionTypes::DELETE, PermissionTypes::LIVE],
            $contexts['Sulu']['Abbreviations'][Abbreviation::SECURITY_CONTEXT]
        );
    }

    public function testConfigKey(): void
    {
        $this->assertSame('sulu_abbreviations', $this->createAdmin()->getConfigKey());
    }
}
