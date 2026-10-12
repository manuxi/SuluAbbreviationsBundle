<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\DependencyInjection;

use Manuxi\SuluAbbreviationsBundle\DependencyInjection\SuluAbbreviationsExtension;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Routing\AbbreviationsRouteDefaultsProvider;
use Manuxi\SuluAbbreviationsBundle\SuluAbbreviationsBundle;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AutomationBundle\SuluAutomationBundle;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SuluAbbreviationsExtensionTest extends TestCase
{
    /**
     * @param array<string, class-string> $bundles
     */
    private function loadContainer(array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);

        (new SuluAbbreviationsExtension())->load([], $container);

        return $container;
    }

    public function testAbbreviationRepositoryIsRegisteredOnce(): void
    {
        $container = $this->loadContainer();

        $repositoryDefinitions = array_filter(
            $container->findTaggedServiceIds('doctrine.repository_service'),
            static fn (string $id): bool => AbbreviationRepository::class === ($container->getDefinition($id)->getClass() ?? $id),
            \ARRAY_FILTER_USE_KEY
        );

        $this->assertCount(1, $repositoryDefinitions);
        $this->assertTrue($container->hasAlias('sulu_abbreviations.repository.abbreviation'));
        $this->assertSame(
            AbbreviationRepository::class,
            (string) $container->getAlias('sulu_abbreviations.repository.abbreviation')
        );
    }

    public function testRouteDefaultsProviderIsRegisteredOnce(): void
    {
        $container = $this->loadContainer();

        $providers = array_filter(
            array_keys($container->findTaggedServiceIds('sulu_route.defaults_provider')),
            static fn (string $id): bool => AbbreviationsRouteDefaultsProvider::class === $container->getDefinition($id)->getClass()
        );

        $this->assertCount(1, $providers);
        $this->assertTrue($container->hasAlias(AbbreviationsRouteDefaultsProvider::class));
    }

    public function testContentTypeAliasesAreUnique(): void
    {
        $container = $this->loadContainer();

        $aliases = [];
        foreach ($container->findTaggedServiceIds('sulu.content.type') as $tags) {
            foreach ($tags as $attributes) {
                $aliases[] = $attributes['alias'];
            }
        }

        sort($aliases);
        $this->assertSame(['abbreviation_selection', 'single_abbreviation_selection'], $aliases);
    }

    public function testPrependDoesNotConfigureFramework(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());

        (new SuluAbbreviationsExtension())->prepend($container);

        $this->assertSame([], $container->getExtensionConfig('framework'));
    }

    public function testTranslationsAreInAutoDiscoveredDirectory(): void
    {
        $directory = \dirname((string) (new \ReflectionClass(SuluAbbreviationsBundle::class))->getFileName()) . '/Resources/translations';

        foreach (['admin.de.yaml', 'admin.en.yaml', 'messages.de.yaml', 'messages.en.yaml'] as $file) {
            $this->assertFileExists($directory . '/' . $file);
        }
    }

    public function testAutomationServicesAreOnlyLoadedWithAutomationBundle(): void
    {
        $this->assertFalse($this->loadContainer()->hasDefinition('sulu_abbreviations.automation.publish_handler'));

        $container = $this->loadContainer(['SuluAutomationBundle' => SuluAutomationBundle::class]);
        $this->assertTrue($container->hasDefinition('sulu_abbreviations.automation.publish_handler'));
        $this->assertTrue($container->hasDefinition('sulu_abbreviations.automation.unpublish_handler'));
    }
}
