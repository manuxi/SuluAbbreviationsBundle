<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Admin;

use Manuxi\SuluAbbreviationsBundle\Controller\Admin\AbbreviationVersionController;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Bundle\ActivityBundle\Infrastructure\Sulu\Admin\View\ActivityViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\DropdownToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ListItemAction;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AutomationBundle\Admin\View\AutomationViewBuilderFactoryInterface;
use Sulu\Bundle\ContentBundle\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactoryInterface;
use Sulu\Bundle\ReferenceBundle\Infrastructure\Sulu\Admin\View\ReferenceViewBuilderFactoryInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

class AbbreviationsAdmin extends Admin
{
    public const NAV_ITEM = 'sulu_abbreviations.abbreviations';

    public const LIST_VIEW      = 'sulu_abbreviations.abbreviations.list';
    public const ADD_FORM_VIEW  = 'sulu_abbreviations.abbreviations.add_form';
    public const EDIT_FORM_VIEW = 'sulu_abbreviations.abbreviations.edit_form';

    public const EDIT_INSIGHTS_VIEW   = 'sulu_abbreviations.abbreviations.edit_form.insights';
    public const EDIT_AUTOMATION_VIEW = 'sulu_abbreviations.abbreviations.edit_form.automation';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly ContentViewBuilderFactoryInterface $contentViewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly ActivityViewBuilderFactoryInterface $activityViewBuilderFactory,
        private readonly ReferenceViewBuilderFactoryInterface $referenceViewBuilderFactory,
        private readonly ?AutomationViewBuilderFactoryInterface $automationViewBuilderFactory = null,
        private readonly bool $versioningEnabled = true,
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        if ($this->hasPermission(PermissionTypes::EDIT)) {
            $rootNavigationItem = new NavigationItem(static::NAV_ITEM);
            $rootNavigationItem->setIcon('su-tag-pen');
            $rootNavigationItem->setPosition(31);
            $rootNavigationItem->setView(static::LIST_VIEW);

            $abbrNavigationItem = new NavigationItem(static::NAV_ITEM);
            $abbrNavigationItem->setPosition(10);
            $abbrNavigationItem->setView(static::LIST_VIEW);

            $rootNavigationItem->addChild($abbrNavigationItem);

            $navigationItemCollection->add($rootNavigationItem);
        }
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->hasPermission(PermissionTypes::EDIT)) {
            return;
        }

        $locales     = $this->webspaceManager->getAllLocales();
        $resourceKey = Abbreviation::RESOURCE_KEY;

        $listToolbarActions = [];
        if ($this->hasPermission(PermissionTypes::ADD)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.add');
        }
        if ($this->hasPermission(PermissionTypes::DELETE)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.delete');
        }
        if ($this->hasPermission(PermissionTypes::VIEW)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.export');
        }

        $viewCollection->add(
            $this->viewBuilderFactory
                ->createListViewBuilder(static::LIST_VIEW, '/abbreviations/:locale')
                ->setResourceKey($resourceKey)
                ->setListKey(Abbreviation::LIST_KEY)
                ->setTitle('sulu_abbreviations.abbreviations')
                ->addListAdapters(['table'])
                ->addLocales($locales)
                ->setDefaultLocale($locales[0])
                ->setAddView(static::ADD_FORM_VIEW)
                ->setEditView(static::EDIT_FORM_VIEW)
                ->addToolbarActions($listToolbarActions)
        );

        $viewCollection->add(
            $this->viewBuilderFactory
                ->createResourceTabViewBuilder(static::ADD_FORM_VIEW, '/abbreviations/:locale/add')
                ->setResourceKey($resourceKey)
                ->setBackView(static::LIST_VIEW)
                ->addLocales($locales)
        );

        $viewCollection->add(
            $this->viewBuilderFactory
                ->createResourceTabViewBuilder(static::EDIT_FORM_VIEW, '/abbreviations/:locale/:id')
                ->setResourceKey($resourceKey)
                ->setBackView(static::LIST_VIEW)
                ->setTitleProperty('name')
                ->addLocales($locales)
        );

        foreach ($this->contentViewBuilderFactory->createViews(
            Abbreviation::class,
            static::EDIT_FORM_VIEW,
            static::ADD_FORM_VIEW,
            Abbreviation::SECURITY_CONTEXT,
            $this->getFormToolbarActions(\count($locales) > 1)
        ) as $viewBuilder) {
            $viewCollection->add($viewBuilder);
        }

        $this->configureInsightsViews($viewCollection);
        $this->configureAutomationView($viewCollection);
    }

    /**
     * @return mixed[]
     */
    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'Abbreviations' => [
                    Abbreviation::SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::ADD,
                        PermissionTypes::EDIT,
                        PermissionTypes::DELETE,
                        PermissionTypes::LIVE,
                    ],
                ],
            ],
        ];
    }

    public function getConfigKey(): ?string
    {
        return 'sulu_abbreviations';
    }

    /**
     * @return array<string, ToolbarAction>
     */
    private function getFormToolbarActions(bool $multipleLocales): array
    {
        $toolbarActions = $this->contentViewBuilderFactory->getDefaultToolbarActions(Abbreviation::class);

        $deleteVisibleCondition   = '(!_permissions || _permissions.delete)';
        $toolbarActions['delete'] = new DropdownToolbarAction(
            'sulu_admin.delete',
            'su-trash-alt',
            [
                new ToolbarAction('sulu_admin.delete', ['visible_condition' => $deleteVisibleCondition]),
                new ToolbarAction('sulu_admin.delete', [
                    'visible_condition' => $deleteVisibleCondition . ' && id != null',
                    'delete_locale'     => true,
                ]),
            ]
        );

        $editActions = [];
        if ($multipleLocales) {
            $editActions[] = new ToolbarAction('sulu_admin.copy_locale', [
                'visible_condition' => '(!_permissions || _permissions.edit) && id != null',
            ]);
        }

        $editToolbarAction = $toolbarActions['edit'] ?? null;
        if ($editToolbarAction instanceof DropdownToolbarAction) {
            /** @var ToolbarAction[] $defaultEditActions */
            $defaultEditActions = $editToolbarAction->getOptions()['toolbarActions'] ?? [];
            $editActions        = array_merge($editActions, $defaultEditActions);
        }

        $toolbarActions['edit'] = new DropdownToolbarAction('sulu_admin.edit', 'su-pen', $editActions);

        return $toolbarActions;
    }

    private function configureInsightsViews(ViewCollection $viewCollection): void
    {
        $hasActivity   = $this->activityViewBuilderFactory->hasActivityListPermission();
        $hasReferences = $this->referenceViewBuilderFactory->hasReferenceListPermission();

        if (!$hasActivity && !$hasReferences && !$this->versioningEnabled) {
            return;
        }

        $viewCollection->add(
            $this->viewBuilderFactory
                ->createResourceTabViewBuilder(static::EDIT_INSIGHTS_VIEW, '/insights')
                ->setResourceKey(Abbreviation::RESOURCE_KEY)
                ->setTabOrder(6144)
                ->setTabTitle('sulu_admin.insights')
                ->setTitleProperty('')
                ->setParent(static::EDIT_FORM_VIEW)
        );

        if ($hasActivity) {
            $viewCollection->add(
                $this->activityViewBuilderFactory
                    ->createActivityListViewBuilder(
                        static::EDIT_INSIGHTS_VIEW . '.activity',
                        '/activities',
                        Abbreviation::RESOURCE_KEY
                    )
                    ->setParent(static::EDIT_INSIGHTS_VIEW)
            );
        }

        if ($this->versioningEnabled) {
            $viewCollection->add(
                $this->viewBuilderFactory
                    ->createListViewBuilder(static::EDIT_INSIGHTS_VIEW . '.versions', '/versions')
                    ->setTabTitle('sulu_admin.versions')
                    ->setResourceKey(AbbreviationVersionController::RESOURCE_KEY)
                    ->setListKey(AbbreviationVersionController::RESOURCE_KEY)
                    ->addListAdapters(['table'])
                    ->addAdapterOptions(['table' => ['skin' => 'flat']])
                    ->disableTabGap()
                    ->disableSearching()
                    ->disableSelection()
                    ->disableColumnOptions()
                    ->disableFiltering()
                    ->addRouterAttributesToListRequest(['id'])
                    ->addItemActions([new ListItemAction('restore_version', ['success_view' => static::EDIT_FORM_VIEW])])
                    ->setParent(static::EDIT_INSIGHTS_VIEW)
            );
        }

        if ($hasReferences) {
            $viewCollection->add(
                $this->referenceViewBuilderFactory
                    ->createReferenceListViewBuilder(
                        static::EDIT_INSIGHTS_VIEW . '.references',
                        '/references',
                        Abbreviation::RESOURCE_KEY
                    )
                    ->setParent(static::EDIT_INSIGHTS_VIEW)
            );
        }
    }

    private function configureAutomationView(ViewCollection $viewCollection): void
    {
        if (null === $this->automationViewBuilderFactory || !$this->hasPermission(PermissionTypes::LIVE)) {
            return;
        }

        $viewCollection->add(
            $this->automationViewBuilderFactory
                ->createTaskListViewBuilder(static::EDIT_AUTOMATION_VIEW, '/automation', Abbreviation::class)
                ->setTabOrder(5120)
                ->setParent(static::EDIT_FORM_VIEW)
        );
    }

    private function hasPermission(string $permission): bool
    {
        return $this->securityChecker->hasPermission(Abbreviation::SECURITY_CONTEXT, $permission);
    }
}
