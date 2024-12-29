<?php

namespace KuKu\SFSystemToolsBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class KuKuSFSystemToolsExtension extends Extension
{

public function getAlias(): string
{
    return 'kuku_sf_system_tools'; // TODO: Change the autogenerated stub
}

    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.xml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $definition = $container->getDefinition('kuku_sf_system_tools.system_info');
        $definition->setArgument(0, $config['expose_sf_version']);
        $definition->setArgument(1, $config['expose_php_version']);
    }
}
