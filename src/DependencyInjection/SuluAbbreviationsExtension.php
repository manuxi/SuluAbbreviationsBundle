<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\DependencyInjection;

use Manuxi\SuluAbbreviationsBundle\Admin\AbbreviationsAdmin;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationsSettings;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SuluAbbreviationsExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $container->setParameter('sulu_abbreviations.versioning.max_versions', $config['versioning']['max_versions']);
        $container->setParameter('sulu_abbreviations.twig.auto_abbr', $config['twig']['auto_abbr']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('doctrine')) {
            $container->prependExtensionConfig(
                'doctrine',
                [
                    'orm' => [
                        'mappings' => [
                            'SuluAbbreviationsBundle' => [
                                'type'   => 'xml',
                                'dir'    => __DIR__ . '/../Resources/config/doctrine',
                                'prefix' => 'Manuxi\SuluAbbreviationsBundle\Entity',
                                'alias'  => 'SuluAbbreviationsBundle',
                            ],
                        ],
                    ],
                ]
            );
        }

        if ($container->hasExtension('sulu_core')) {
            $container->prependExtensionConfig(
                'sulu_core',
                [
                    'content' => [
                        'structure' => [
                            'paths' => [
                                Abbreviation::TEMPLATE_TYPE => [
                                    'path' => __DIR__ . '/../Resources/config/templates/abbreviations',
                                    'type' => Abbreviation::TEMPLATE_TYPE,
                                ],
                            ],
                            'default_type' => [
                                Abbreviation::TEMPLATE_TYPE => Abbreviation::DEFAULT_TEMPLATE,
                            ],
                        ],
                    ],
                ]
            );
        }

        if ($container->hasExtension('sulu_search')) {
            $container->prependExtensionConfig(
                'sulu_search',
                [
                    'indexes' => [
                        'abbreviations' => [
                            'name'             => 'sulu_abbreviations.search.index.abbreviations',
                            'icon'             => 'su-tag-pen',
                            'security_context' => Abbreviation::SECURITY_CONTEXT,
                            'view'             => [
                                'name'           => AbbreviationsAdmin::EDIT_FORM_VIEW,
                                'result_to_view' => [
                                    'id'     => 'id',
                                    'locale' => 'locale',
                                ],
                            ],
                        ],
                        'abbreviations_published' => [
                            'name'             => 'sulu_abbreviations.search.index.abbreviations_published',
                            'icon'             => 'su-tag-pen',
                            'security_context' => Abbreviation::SECURITY_CONTEXT,
                            'view'             => [
                                'name'           => AbbreviationsAdmin::EDIT_FORM_VIEW,
                                'result_to_view' => [
                                    'id'     => 'id',
                                    'locale' => 'locale',
                                ],
                            ],
                        ],
                    ],
                ]
            );

            foreach ($container->getExtensionConfig('sulu_search') as $suluSearchConfig) {
                if (isset($suluSearchConfig['website']['indexes'])) {
                    $container->prependExtensionConfig(
                        'sulu_search',
                        [
                            'website' => [
                                'indexes' => [
                                    Abbreviation::RESOURCE_KEY => Abbreviation::RESOURCE_KEY . '_published',
                                ],
                            ],
                        ]
                    );

                    break;
                }
            }
        }

        if ($container->hasExtension('sulu_route')) {
            $container->prependExtensionConfig(
                'sulu_route',
                [
                    'mappings' => [
                        Abbreviation::class => [
                            'generator' => 'schema',
                            'options'   => [
                                'route_schema' => '/{translator.trans("sulu_abbreviations.url_prefix")}/{object["name"]}',
                            ],
                            'resource_key' => Abbreviation::RESOURCE_KEY,
                        ],
                    ],
                ]
            );
        }

        if ($container->hasExtension('sulu_admin')) {
            $container->prependExtensionConfig(
                'sulu_admin',
                [
                    'lists' => [
                        'directories' => [
                            __DIR__ . '/../Resources/config/lists',
                        ],
                    ],
                    'forms' => [
                        'directories' => [
                            __DIR__ . '/../Resources/config/forms',
                        ],
                    ],
                    'resources' => [
                        Abbreviation::RESOURCE_KEY => [
                            'routes' => [
                                'list'   => 'sulu_abbreviations.get_abbreviations',
                                'detail' => 'sulu_abbreviations.get_abbreviation',
                            ],
                            'views' => [
                                'list'   => AbbreviationsAdmin::LIST_VIEW,
                                'detail' => AbbreviationsAdmin::EDIT_FORM_VIEW,
                            ],
                        ],
                        AbbreviationsSettings::RESOURCE_KEY => [
                            'routes' => [
                                'detail' => 'sulu_abbreviations.get_abbreviations_settings',
                            ],
                        ],
                    ],
                    'field_type_options' => [
                        'selection' => [
                            'abbreviation_selection' => [
                                'default_type' => 'list_overlay',
                                'resource_key' => Abbreviation::RESOURCE_KEY,
                                'view'         => [
                                    'name'           => AbbreviationsAdmin::EDIT_FORM_VIEW,
                                    'result_to_view' => [
                                        'id' => 'id',
                                    ],
                                ],
                                'types' => [
                                    'list_overlay' => [
                                        'adapter'            => 'table',
                                        'list_key'           => Abbreviation::LIST_KEY,
                                        'display_properties' => [
                                            'name',
                                        ],
                                        'icon'          => 'su-tag-pen',
                                        'label'         => 'sulu_abbreviations.abbreviation_selection_label',
                                        'overlay_title' => 'sulu_abbreviations.select_abbreviation',
                                    ],
                                ],
                            ],
                        ],
                        'single_selection' => [
                            'single_abbreviation_selection' => [
                                'default_type' => 'list_overlay',
                                'resource_key' => Abbreviation::RESOURCE_KEY,
                                'view'         => [
                                    'name'           => AbbreviationsAdmin::EDIT_FORM_VIEW,
                                    'result_to_view' => [
                                        'id' => 'id',
                                    ],
                                ],
                                'types' => [
                                    'list_overlay' => [
                                        'adapter'            => 'table',
                                        'list_key'           => Abbreviation::LIST_KEY,
                                        'display_properties' => [
                                            'name',
                                        ],
                                        'icon'          => 'su-tag-pen',
                                        'empty_text'    => 'sulu_abbreviations.no_abbreviation_selected',
                                        'overlay_title' => 'sulu_abbreviations.select_abbreviation',
                                    ],
                                    'auto_complete' => [
                                        'display_property'  => 'name',
                                        'search_properties' => [
                                            'name',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]
            );
        }
    }
}
