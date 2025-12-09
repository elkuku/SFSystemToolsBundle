<?php

namespace KuKu\SFSystemToolsBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class KuKuSFSystemToolsBundle extends AbstractBundle
{
    public string $extensionAlias = 'kuku_sf_system_tools';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->booleanNode('expose_sf_version')->defaultFalse()->end()
            ->booleanNode('expose_php_version')->defaultFalse()->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            // Service
            ->set('kuku_sf_system_tools.system_info', \KuKu\SFSystemToolsBundle\Service\SystemInfo::class)
            ->args([
                $config['expose_sf_version'],
                $config['expose_php_version'],
            ])
            ->alias(\KuKu\SFSystemToolsBundle\Service\SystemInfo::class, 'kuku_sf_system_tools.system_info')

            // Controller
            ->set('kuku_sf_system_tools.system_info_controller', \KuKu\SFSystemToolsBundle\Controller\SFSystemToolsController::class)
            ->args([
                service('kuku_sf_system_tools.system_info')
            ])
            ->tag('controller.service_arguments');
    }
}
