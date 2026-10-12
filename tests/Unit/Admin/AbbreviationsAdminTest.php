<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Admin;

use Manuxi\SuluAbbreviationsBundle\Admin\AbbreviationsAdmin;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

class AbbreviationsAdminTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @param string[] $permissions
     */
    private function configureViews(array $permissions): ViewCollection
    {
        $securityChecker = $this->prophesize(SecurityCheckerInterface::class);
        $securityChecker->hasPermission(Abbreviation::SECURITY_CONTEXT, Argument::type('string'))
            ->will(static fn (array $args): bool => \in_array($args[1], $permissions, true));

        $webspaceManager = $this->prophesize(WebspaceManagerInterface::class);
        $webspaceManager->getAllLocales()->willReturn(['en', 'de']);

        $admin = new AbbreviationsAdmin(
            new ViewBuilderFactory(),
            $securityChecker->reveal(),
            $webspaceManager->reveal()
        );

        $viewCollection = new ViewCollection();
        $admin->configureViews($viewCollection);

        return $viewCollection;
    }

    public function testNoViewsWithoutEditPermission(): void
    {
        $this->assertSame([], $this->configureViews([PermissionTypes::VIEW])->all());
    }

    public function testSeoExcerptAndSettingsTabsUseIdPreviewCondition(): void
    {
        $viewCollection = $this->configureViews([PermissionTypes::VIEW, PermissionTypes::EDIT]);

        foreach ([
            AbbreviationsAdmin::EDIT_FORM_DETAILS_VIEW,
            AbbreviationsAdmin::EDIT_FORM_VIEW_SEO,
            AbbreviationsAdmin::EDIT_FORM_VIEW_EXCERPT,
            AbbreviationsAdmin::EDIT_FORM_VIEW_SETTINGS,
        ] as $viewName) {
            $this->assertSame(
                'id != null',
                $viewCollection->get($viewName)->getView()->getOption('previewCondition'),
                $viewName
            );
        }
    }
}
