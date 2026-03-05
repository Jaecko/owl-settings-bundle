<?php

namespace OwlConcept\SettingsBundle\DependencyInjection;

use OwlConcept\SettingsBundle\Service\SettingsService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class OwlSettingsExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('owl_settings.css_class_prefix', $config['css_class_prefix']);
        $container->setParameter('owl_settings.cache_pool', $config['cache_pool']);
        $container->setParameter('owl_settings.cache_ttl', $config['cache_ttl']);
        $container->setParameter('owl_settings.settings_table', $config['settings_table']);
        $container->setParameter('owl_settings.preferences_table', $config['preferences_table']);
        $container->setParameter('owl_settings.user_identifier_method', $config['user_identifier_method']);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');

        // Inject the configured cache pool dynamically
        $settingsServiceDef = $container->getDefinition(SettingsService::class);
        $settingsServiceDef->replaceArgument('$cache', new Reference($config['cache_pool']));
    }
}
