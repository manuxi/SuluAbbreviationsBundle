<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Admin;

use Manuxi\SuluAbbreviationsBundle\Admin\AbbreviationsAdmin;
use Manuxi\SuluAbbreviationsBundle\Admin\SettingsAdmin;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationsSettings;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

class SettingsAdminTest extends TestCase
{
    use ProphecyTrait;

    private function createAdmin(bool $allowed): SettingsAdmin
    {
        $securityChecker = $this->prophesize(SecurityCheckerInterface::class);
        $securityChecker->hasPermission(AbbreviationsSettings::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn($allowed);

        return new SettingsAdmin(new ViewBuilderFactory(), $securityChecker->reveal());
    }

    public function testNavigationItemIsAddedBelowTheModuleItem(): void
    {
        $collection = new NavigationItemCollection();
        $collection->add(new NavigationItem(AbbreviationsAdmin::NAV_ITEM));

        $this->createAdmin(true)->configureNavigationItems($collection);

        $children = $collection->get(AbbreviationsAdmin::NAV_ITEM)->getChildren();
        $this->assertCount(1, $children);
        $this->assertSame(SettingsAdmin::TAB_VIEW, $children[0]->getView());
    }

    public function testNavigationItemIsMissingWithoutPermission(): void
    {
        $collection = new NavigationItemCollection();
        $collection->add(new NavigationItem(AbbreviationsAdmin::NAV_ITEM));

        $this->createAdmin(false)->configureNavigationItems($collection);

        $this->assertCount(0, $collection->get(AbbreviationsAdmin::NAV_ITEM)->getChildren());
    }

    public function testViewsAreRegistered(): void
    {
        $collection = new ViewCollection();
        $this->createAdmin(true)->configureViews($collection);

        $this->assertTrue($collection->has(SettingsAdmin::TAB_VIEW));
        $this->assertTrue($collection->has(SettingsAdmin::FORM_VIEW));
        $this->assertSame(SettingsAdmin::TAB_VIEW, $collection->get(SettingsAdmin::FORM_VIEW)->getView()->getParent());
    }

    public function testNoViewsWithoutPermission(): void
    {
        $collection = new ViewCollection();
        $this->createAdmin(false)->configureViews($collection);

        $this->assertSame([], $collection->all());
    }

    public function testSecurityContexts(): void
    {
        $contexts = $this->createAdmin(true)->getSecurityContexts();

        $this->assertSame(
            [PermissionTypes::VIEW, PermissionTypes::EDIT],
            $contexts['Sulu']['Abbreviations'][AbbreviationsSettings::SECURITY_CONTEXT]
        );
        $this->assertSame('sulu_abbreviations.config.title', $this->createAdmin(true)->getConfigKey());
    }
}
