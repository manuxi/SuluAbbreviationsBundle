<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Controller\Admin;

use Doctrine\Common\Annotations\AnnotationReader;
use HandcraftedInTheAlps\RestRoutingBundle\Inflector\DoctrineInflector;
use HandcraftedInTheAlps\RestRoutingBundle\Routing\Loader\Reader\RestActionReader;
use HandcraftedInTheAlps\RestRoutingBundle\Routing\Loader\Reader\RestControllerReader;
use Manuxi\SuluAbbreviationsBundle\Controller\Admin\SettingsController;
use Manuxi\SuluAbbreviationsBundle\DependencyInjection\SuluAbbreviationsExtension;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationsSettings;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\DependencyInjection\SuluAdminExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SettingsControllerRoutingTest extends TestCase
{
    public function testRoutesMatchRegisteredAdminResource(): void
    {
        $annotationReader = new AnnotationReader();
        $actionReader     = new RestActionReader($annotationReader, null, new DoctrineInflector(), false);
        $controllerReader = new RestControllerReader($actionReader, $annotationReader);

        $routes = $controllerReader->read(new \ReflectionClass(SettingsController::class));

        // A path below /abbreviations would collide with the abbreviation detail route /abbreviations/{id}
        $this->assertNotNull($routes->get('get_abbreviations-settings'));
        $this->assertNotNull($routes->get('put_abbreviations-settings'));
        $this->assertSame('/abbreviations-settings', $routes->get('get_abbreviations-settings')->getPath());

        $container = new ContainerBuilder();
        $container->registerExtension(new SuluAdminExtension());
        (new SuluAbbreviationsExtension())->prepend($container);

        $adminConfig = array_merge_recursive(...$container->getExtensionConfig('sulu_admin'));
        $resource    = $adminConfig['resources'][AbbreviationsSettings::RESOURCE_KEY];

        $this->assertSame('abbreviations_settings', AbbreviationsSettings::RESOURCE_KEY);
        $this->assertSame('sulu_abbreviations.get_abbreviations-settings', $resource['routes']['detail']);
        $this->assertArrayNotHasKey('abbreviations-settings', $adminConfig['resources']);
    }
}
