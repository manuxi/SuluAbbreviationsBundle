<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Admin;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\TogglerToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

class AbbreviationsAdmin extends Admin
{
    public const NAV_ITEM = 'sulu_abbreviations.abbreviations';

    public const LIST_VIEW = 'sulu_abbreviations.abbreviations.list';
    public const ADD_FORM_VIEW = 'sulu_abbreviations.abbreviations.add_form';
    public const ADD_FORM_DETAILS_VIEW = 'sulu_abbreviations.abbreviations.add_form.details';
    public const EDIT_FORM_VIEW = 'sulu_abbreviations.abbreviations.edit_form';
    public const EDIT_FORM_DETAILS_VIEW = 'sulu_abbreviations.abbreviations.edit_form.details';
    public const SECURITY_CONTEXT = 'sulu.modules.abbreviation';

    //seo,excerpt, etc
    public const EDIT_FORM_VIEW_SEO = 'sulu_abbreviations.edit_form.seo';
    public const EDIT_FORM_VIEW_EXCERPT = 'sulu_abbreviations.edit_form.excerpt';
    public const EDIT_FORM_VIEW_SETTINGS = 'sulu_abbreviations.edit_form.settings';

    private ViewBuilderFactoryInterface $viewBuilderFactory;
    private SecurityCheckerInterface $securityChecker;
    private WebspaceManagerInterface $webspaceManager;

    private ?array $types = null;

    public function __construct(
        ViewBuilderFactoryInterface $viewBuilderFactory,
        SecurityCheckerInterface $securityChecker,
        WebspaceManagerInterface $webspaceManager
    ) {
        $this->viewBuilderFactory = $viewBuilderFactory;
        $this->securityChecker    = $securityChecker;
        $this->webspaceManager    = $webspaceManager;
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        if ($this->securityChecker->hasPermission(Abbreviation::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            $rootNavigationItem = new NavigationItem(static::NAV_ITEM);
            $rootNavigationItem->setIcon('su-tag-pen');
            $rootNavigationItem->setPosition(30);
            $rootNavigationItem->setView(static::LIST_VIEW);

            // Configure a NavigationItem with a View
            $abbrNavigationItem = new NavigationItem(static::NAV_ITEM);
            $abbrNavigationItem->setPosition(10);
            $abbrNavigationItem->setView(static::LIST_VIEW);

            $rootNavigationItem->addChild($abbrNavigationItem);

            $navigationItemCollection->add($rootNavigationItem);
        }
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->securityChecker->hasPermission(Abbreviation::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            return;
        }

        $formToolbarActions = [];
        $listToolbarActions = [];

        $locales = $this->webspaceManager->getAllLocales();

        if ($this->securityChecker->hasPermission(Abbreviation::SECURITY_CONTEXT, PermissionTypes::ADD)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.add');
        }

        if ($this->securityChecker->hasPermission(Abbreviation::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            $formToolbarActions[] = new ToolbarAction('sulu_admin.save');
        }

        if ($this->securityChecker->hasPermission(Abbreviation::SECURITY_CONTEXT, PermissionTypes::DELETE)) {
            $formToolbarActions[] = new ToolbarAction('sulu_admin.delete');
            $listToolbarActions[] = new ToolbarAction('sulu_admin.delete');
        }

        if ($this->securityChecker->hasPermission(Abbreviation::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.export');
        }

        if ($this->securityChecker->hasPermission(Abbreviation::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            // Configure Abbreviation List View
            $listView = $this->viewBuilderFactory
                ->createListViewBuilder(static::LIST_VIEW, '/abbreviation/:locale')
                ->setResourceKey(Abbreviation::RESOURCE_KEY)
                ->setListKey(Abbreviation::LIST_KEY)
                ->setTitle('sulu_abbreviations.abbreviations')
                ->addListAdapters(['table'])
                ->addLocales($locales)
                ->setDefaultLocale($locales[0])
                ->setAddView(static::ADD_FORM_VIEW)
                ->setEditView(static::EDIT_FORM_VIEW)
                ->addToolbarActions($listToolbarActions);
            $viewCollection->add($listView);

            // Configure Abbreviation Add View
            $addFormView = $this->viewBuilderFactory
                ->createResourceTabViewBuilder(static::ADD_FORM_VIEW, '/abbreviation/:locale/add')
                ->setResourceKey(Abbreviation::RESOURCE_KEY)
                ->setBackView(static::LIST_VIEW)
                ->addLocales($locales);
            $viewCollection->add($addFormView);

            $addDetailsFormView = $this->viewBuilderFactory
                ->createFormViewBuilder(static::ADD_FORM_DETAILS_VIEW, '/details')
                ->setResourceKey(Abbreviation::RESOURCE_KEY)
                ->setFormKey(Abbreviation::FORM_KEY)
                ->setTabTitle('sulu_admin.details')
                ->setEditView(static::EDIT_FORM_VIEW)
                ->addToolbarActions($formToolbarActions)
                ->setParent(static::ADD_FORM_VIEW);
            $viewCollection->add($addDetailsFormView);

            // Configure Abbreviation Edit View
            $editFormView = $this->viewBuilderFactory
                ->createResourceTabViewBuilder(static::EDIT_FORM_VIEW, '/abbreviation/:locale/:id')
                ->setResourceKey(Abbreviation::RESOURCE_KEY)
                ->setBackView(static::LIST_VIEW)
                ->setTitleProperty('name')
                ->addLocales($locales);
            $viewCollection->add($editFormView);

            //publish/unpublish toolbar actions
            $formToolbarActions = [
                new ToolbarAction('sulu_admin.save'),
                new ToolbarAction('sulu_admin.delete'),
                new TogglerToolbarAction(
                    'sulu_abbreviations.published',
                    'published',
                    'publish',
                    'unpublish'
                ),
            ];

            $editDetailsFormView = $this->viewBuilderFactory
                ->createPreviewFormViewBuilder(static::EDIT_FORM_DETAILS_VIEW, '/details')
                ->setPreviewCondition('id != null')
                ->setResourceKey(Abbreviation::RESOURCE_KEY)
                ->setFormKey(Abbreviation::FORM_KEY)
                ->setTabTitle('sulu_admin.details')
                ->addToolbarActions($formToolbarActions)
                ->setParent(static::EDIT_FORM_VIEW);
            $viewCollection->add($editDetailsFormView);

            //seo,excerpt, etc
            $formToolbarActionsWithoutType = [];
            $previewCondition              = 'nodeType == 1';

            if ($this->securityChecker->hasPermission(Abbreviation::SECURITY_CONTEXT, PermissionTypes::ADD)) {
                $listToolbarActions[] = new ToolbarAction('sulu_admin.add');
            }

            $formToolbarActionsWithoutType[] = new ToolbarAction('sulu_admin.save');

            $viewCollection->add(
                $this->viewBuilderFactory
                    ->createPreviewFormViewBuilder(static::EDIT_FORM_VIEW_SEO, '/seo')
//                    ->disablePreviewWebspaceChooser()
                    ->setResourceKey(Abbreviation::RESOURCE_KEY)
                    ->setFormKey('page_seo')
                    ->setTabTitle('sulu_page.seo')
//                    ->setTabCondition('nodeType == 1 && shadowOn == false')
                    ->addToolbarActions($formToolbarActionsWithoutType)
//                    ->addRouterAttributesToFormRequest($routerAttributesToFormRequest)
                    ->setPreviewCondition($previewCondition)
                    ->setTitleVisible(true)
                    ->setTabOrder(2048)
                    ->setParent(static::EDIT_FORM_VIEW)
            );
            $viewCollection->add(
                $this->viewBuilderFactory
                    ->createPreviewFormViewBuilder(static::EDIT_FORM_VIEW_EXCERPT, '/excerpt')
//                    ->disablePreviewWebspaceChooser()
                    ->setResourceKey(Abbreviation::RESOURCE_KEY)
                    ->setFormKey('page_excerpt')
                    ->setTabTitle('sulu_page.excerpt')
//                    ->setTabCondition('(nodeType == 1 || nodeType == 4) && shadowOn == false')
                    ->addToolbarActions($formToolbarActionsWithoutType)
//                    ->addRouterAttributesToFormRequest($routerAttributesToFormRequest)
//                    ->addRouterAttributesToFormMetadata($routerAttributesToFormMetadata)
                    ->setPreviewCondition($previewCondition)
                    ->setTitleVisible(true)
                    ->setTabOrder(3072)
                    ->setParent(static::EDIT_FORM_VIEW)
            );

            $viewCollection->add(
                $this->viewBuilderFactory
                    ->createPreviewFormViewBuilder(static::EDIT_FORM_VIEW_SETTINGS, '/settings')
                    ->disablePreviewWebspaceChooser()
                    ->setResourceKey(Abbreviation::RESOURCE_KEY)
                    ->setFormKey('abbreviation_settings')
                    ->setTabTitle('sulu_page.settings')
                    ->addToolbarActions($formToolbarActionsWithoutType)
                    ->setPreviewCondition($previewCondition)
                    ->setTitleVisible(true)
                    ->setTabOrder(4096)
                    ->setParent(static::EDIT_FORM_VIEW)
            );
        }
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
}
