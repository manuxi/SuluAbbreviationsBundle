<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\DependencyInjection;

use Manuxi\SuluAbbreviationsBundle\Admin\AbbreviationsAdmin;
use Manuxi\SuluAbbreviationsBundle\DependencyInjection\SuluAbbreviationsExtension;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationsSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class SuluAbbreviationsExtensionTest extends TestCase
{
    /**
     * @param string[]                                        $extensions
     * @param array<string, array<int, array<string, mixed>>> $existingConfigs
     */
    private function prepend(array $extensions, array $existingConfigs = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        foreach ($extensions as $name) {
            $container->registerExtension(new class($name) extends Extension {
                public function __construct(private readonly string $alias)
                {
                }

                public function getAlias(): string
                {
                    return $this->alias;
                }

                public function load(array $configs, ContainerBuilder $container): void
                {
                }
            });
        }

        foreach ($existingConfigs as $name => $configs) {
            foreach ($configs as $config) {
                $container->prependExtensionConfig($name, $config);
            }
        }

        (new SuluAbbreviationsExtension())->prepend($container);

        return $container;
    }

    public function testNothingIsPrependedWithoutTheTargetExtensions(): void
    {
        $container = $this->prepend([]);

        $this->assertSame([], $container->getExtensionConfig('doctrine'));
        $this->assertSame([], $container->getExtensionConfig('sulu_admin'));
    }

    public function testDoctrineMapping(): void
    {
        $config = $this->prepend(['doctrine'])->getExtensionConfig('doctrine')[0];

        $mapping = $config['orm']['mappings']['SuluAbbreviationsBundle'];
        $this->assertSame('xml', $mapping['type']);
        $this->assertSame('Manuxi\SuluAbbreviationsBundle\Entity', $mapping['prefix']);
        $this->assertDirectoryExists($mapping['dir']);
        $this->assertFileExists($mapping['dir'] . '/Abbreviation.orm.xml');
    }

    public function testTemplatePathAndDefaultType(): void
    {
        $config = $this->prepend(['sulu_core'])->getExtensionConfig('sulu_core')[0];

        $structure = $config['content']['structure'];
        $this->assertSame('abbreviation', $structure['paths']['abbreviation']['type']);
        $this->assertFileExists($structure['paths']['abbreviation']['path'] . '/abbreviation_default.xml');
        $this->assertSame('abbreviation_default', $structure['default_type']['abbreviation']);
    }

    public function testRouteMapping(): void
    {
        $config = $this->prepend(['sulu_route'])->getExtensionConfig('sulu_route')[0];

        $mapping = $config['mappings'][Abbreviation::class];
        $this->assertSame('schema', $mapping['generator']);
        $this->assertSame(Abbreviation::RESOURCE_KEY, $mapping['resource_key']);
        $this->assertStringContainsString('sulu_abbreviations.url_prefix', $mapping['options']['route_schema']);
        $this->assertStringContainsString('object["name"]', $mapping['options']['route_schema']);
    }

    public function testAdminConfiguration(): void
    {
        $config = $this->prepend(['sulu_admin'])->getExtensionConfig('sulu_admin')[0];

        $this->assertSame('sulu_abbreviations.get_abbreviations', $config['resources'][Abbreviation::RESOURCE_KEY]['routes']['list']);
        $this->assertSame('sulu_abbreviations.get_abbreviation', $config['resources'][Abbreviation::RESOURCE_KEY]['routes']['detail']);
        $this->assertSame(AbbreviationsAdmin::EDIT_FORM_VIEW, $config['resources'][Abbreviation::RESOURCE_KEY]['views']['detail']);
        $this->assertSame('sulu_abbreviations.get_abbreviation_versions', $config['resources']['abbreviation_versions']['routes']['list']);
        $this->assertSame('sulu_abbreviations.post_abbreviation_version_trigger', $config['resources']['abbreviation_versions']['routes']['detail']);
        $this->assertSame('sulu_abbreviations.get_abbreviations_settings', $config['resources'][AbbreviationsSettings::RESOURCE_KEY]['routes']['detail']);
        $this->assertArrayHasKey('abbreviation_selection', $config['field_type_options']['selection']);
        $this->assertArrayHasKey('single_abbreviation_selection', $config['field_type_options']['single_selection']);
        $this->assertSame(Abbreviation::LIST_KEY, $config['field_type_options']['selection']['abbreviation_selection']['types']['list_overlay']['list_key']);
    }

    public function testSearchIndexesWithoutWebsiteConfiguration(): void
    {
        $configs = $this->prepend(['sulu_search'])->getExtensionConfig('sulu_search');

        $this->assertCount(1, $configs);
        $this->assertArrayHasKey('abbreviations', $configs[0]['indexes']);
        $this->assertArrayHasKey('abbreviations_published', $configs[0]['indexes']);
        $this->assertSame(Abbreviation::SECURITY_CONTEXT, $configs[0]['indexes']['abbreviations']['security_context']);
    }

    public function testWebsiteSearchIndexIsMappedToThePublishedIndex(): void
    {
        $configs = $this->prepend(['sulu_search'], ['sulu_search' => [['website' => ['indexes' => ['page' => 'page_published']]]]])
            ->getExtensionConfig('sulu_search');

        $merged = array_merge(...$configs);
        $this->assertSame('abbreviations_published', $configs[0]['website']['indexes']['abbreviations']);
        $this->assertArrayHasKey('indexes', $merged);
    }

    public function testLoadRegistersServicesAndParameters(): void
    {
        $container = new ContainerBuilder();
        (new SuluAbbreviationsExtension())->load([['versioning' => ['max_versions' => 5], 'twig' => ['auto_abbr' => true]]], $container);

        $this->assertSame(5, $container->getParameter('sulu_abbreviations.versioning.max_versions'));
        $this->assertTrue($container->getParameter('sulu_abbreviations.twig.auto_abbr'));
        $this->assertTrue($container->hasDefinition(AbbreviationsAdmin::class));
        $this->assertTrue($container->hasDefinition('sulu_abbreviations.reference_store'));
        $this->assertTrue($container->hasDefinition('sulu_abbreviations.data_provider'));
    }

    public function testLoadUsesDefaults(): void
    {
        $container = new ContainerBuilder();
        (new SuluAbbreviationsExtension())->load([], $container);

        $this->assertSame(20, $container->getParameter('sulu_abbreviations.versioning.max_versions'));
        $this->assertFalse($container->getParameter('sulu_abbreviations.twig.auto_abbr'));
    }
}
