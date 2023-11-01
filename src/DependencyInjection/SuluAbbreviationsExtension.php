<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\DependencyInjection;

use Manuxi\SuluAbbreviationsBundle\Admin\AbbreviationsAdmin;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Bundle\PersistenceBundle\DependencyInjection\PersistenceExtensionTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SuluAbbreviationsExtension extends Extension implements PrependExtensionInterface
{
    use PersistenceExtensionTrait;

    /**
     * @param array $configs
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');
        $loader->load('controller.xml');

        $this->configurePersistence($config['objects'], $container);
    }

    public function prepend(ContainerBuilder $container)
    {
        if ($container->hasExtension('sulu_search')) {
            $container->prependExtensionConfig(
                'sulu_search',
                [
                    'indexes' => [
                        'abbreviation' => [
                            'name' => 'sulu_abbreviations.search_name',
                            'icon' => 'su-enter',
                            'security_context' => Abbreviation::SECURITY_CONTEXT,
                            'view' => [
                                'name' => AbbreviationsAdmin::EDIT_FORM_VIEW,
                                'result_to_view' => [
                                    'id' => 'id',
                                    'locale' => 'locale',
                                ],
                            ],

                        ],
/*                        'website' => [
                            "name" => "sulu_abbreviations.search_name",
                            'icon' => 'su-enter',
                            "contexts" => [
                                "abbreviation",
                            ],
                        ],*/
                    ],
                ]
            );
        }

        if ($container->hasExtension('sulu_route')) {
            $container->prependExtensionConfig(
                'sulu_route',
                [
                    'mappings' => [
                        Abbreviation::class => [
                            'generator' => 'schema',
                            'options' => [
                                //@TODO: works not yet as expected, does not translate correctly
                                //see https://github.com/sulu/sulu/pull/5920
                                'route_schema' => '/{translator.trans("sulu_abbreviations.abbreviations")}/{implode("-", object)}'
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
                        'abbreviations' => [
                            'routes' => [
                                'list' => 'sulu_abbreviations.get_abbreviations',
                                'detail' => 'sulu_abbreviations.get_abbreviation',
                            ],
                        ],
                    ],
                    'field_type_options' => [
                        'selection' => [
                            'abbreviation_selection' => [
                                'default_type' => 'list_overlay',
                                'resource_key' => Abbreviation::RESOURCE_KEY,
                                'view' => [
                                    'name' => AbbreviationsAdmin::EDIT_FORM_VIEW,
                                    'result_to_view' => [
                                        'id' => 'id'
                                    ]
                                ],
                                'types' => [
                                    'list_overlay' => [
                                        'adapter' => 'table',
                                        'list_key' => Abbreviation::LIST_KEY,
                                        'display_properties' => [
                                            'name'
                                        ],
                                        'icon' => 'su-enter',
                                        'label' => 'sulu_abbreviations.abbreviations_selection_label',
                                        'overlay_title' => 'sulu_abbreviations.select_abbreviation'
                                    ]
                                ]
                            ]
                        ],
                        'single_selection' => [
                            'single_abbreviation_selection' => [
                                'default_type' => 'list_overlay',
                                'resource_key' => Abbreviation::RESOURCE_KEY,
                                'view' => [
                                    'name' => AbbreviationsAdmin::EDIT_FORM_VIEW,
                                    'result_to_view' => [
                                        'id' => 'id'
                                    ]
                                ],
                                'types' => [
                                    'list_overlay' => [
                                        'adapter' => 'table',
                                        'list_key' => Abbreviation::LIST_KEY,
                                        'display_properties' => [
                                            'name'
                                        ],
                                        'icon' => 'su-enter',
                                        'empty_text' => 'sulu_abbreviations.no_abbreviation_selected',
                                        'overlay_title' => 'sulu_abbreviations.select_abbreviation'
                                    ],
                                    'auto_complete' => [
                                        'display_property' => 'name',
                                        'search_properties' => [
                                            'name'
                                        ]
                                    ]
                                ]
                            ],
                        ]
                    ],
                ]
            );
        }

        $container->loadFromExtension('framework', [
            'default_locale' => 'en',
            'translator' => ['paths' => [__DIR__ . '/../Resources/config/translations/']],
        ]);
    }
}
