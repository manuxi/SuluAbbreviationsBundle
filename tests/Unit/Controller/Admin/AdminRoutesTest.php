<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Controller\Admin;

use Manuxi\SuluAbbreviationsBundle\Controller\Admin\AbbreviationController;
use Manuxi\SuluAbbreviationsBundle\Controller\Admin\AbbreviationVersionController;
use Manuxi\SuluAbbreviationsBundle\Controller\Admin\SettingsController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

class AdminRoutesTest extends TestCase
{
    /**
     * @param class-string $class
     *
     * @return array<string, array{string, string[]}>
     */
    private function collectRoutes(string $class): array
    {
        $reflection = new \ReflectionClass($class);

        $classAttributes = $reflection->getAttributes(Route::class);
        $this->assertCount(1, $classAttributes);
        $prefix = $classAttributes[0]->newInstance()->getPath();
        $this->assertSame('/admin/api', $prefix);

        $routes = [];
        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                $route = $attribute->newInstance();
                $name  = $route->getName();
                $this->assertNotNull($name);
                $routes[$name] = [$prefix . $route->getPath(), $route->getMethods()];
            }
        }

        return $routes;
    }

    public function testAbbreviationRoutes(): void
    {
        $this->assertSame([
            'sulu_abbreviations.get_abbreviations'         => ['/admin/api/abbreviations', ['GET']],
            'sulu_abbreviations.get_abbreviation'          => ['/admin/api/abbreviations/{id}', ['GET']],
            'sulu_abbreviations.post_abbreviation'         => ['/admin/api/abbreviations', ['POST']],
            'sulu_abbreviations.post_abbreviation_trigger' => ['/admin/api/abbreviations/{id}', ['POST']],
            'sulu_abbreviations.put_abbreviation'          => ['/admin/api/abbreviations/{id}', ['PUT']],
            'sulu_abbreviations.delete_abbreviation'       => ['/admin/api/abbreviations/{id}', ['DELETE']],
        ], $this->collectRoutes(AbbreviationController::class));
    }

    public function testVersionRoutes(): void
    {
        $this->assertSame([
            'sulu_abbreviations.get_abbreviation_versions'          => ['/admin/api/abbreviations/{id}/versions', ['GET']],
            'sulu_abbreviations.post_abbreviation_version_trigger'  => ['/admin/api/abbreviations/{id}/versions/{version}', ['POST']],
        ], $this->collectRoutes(AbbreviationVersionController::class));
    }

    public function testSettingsRoutesDoNotCollideWithAbbreviationDetailRoute(): void
    {
        $routes = $this->collectRoutes(SettingsController::class);

        $this->assertSame([
            'sulu_abbreviations.get_abbreviations_settings' => ['/admin/api/abbreviations-settings', ['GET']],
            'sulu_abbreviations.put_abbreviations_settings' => ['/admin/api/abbreviations-settings', ['PUT']],
        ], $routes);
    }
}
