<?php

namespace KuKu\SFSystemToolsBundle;

use KuKu\SFSystemToolsBundle\Controller\AboutController;
use KuKu\SFSystemToolsBundle\Controller\LogviewController;
use KuKu\SFSystemToolsBundle\Controller\SFSystemToolsController;
use KuKu\SFSystemToolsBundle\Service\AboutInfo;
use KuKu\SFSystemToolsBundle\Service\DeployLogParser;
use KuKu\SFSystemToolsBundle\Service\SystemInfo;
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
                ->booleanNode('expose_db_version')
                    ->info('Adds db_platform and db_version to /system/info/. Needs Doctrine DBAL.')
                    ->defaultFalse()
                ->end()
                ->booleanNode('expose_last_deploy')
                    ->info('Adds last_deploy (time and status of the newest run in admin.deploy_log) to /system/info/.')
                    ->defaultFalse()
                ->end()
                ->arrayNode('admin')
                    ->addDefaultsIfNotSet()
                    ->info('The About and deploy log pages. They need symfony/twig-bundle and symfony/security-bundle.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('role')
                            ->info('The role a user needs to see the pages.')
                            ->defaultValue('ROLE_ADMIN')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('base_template')
                            ->info('The template the pages extend. It needs a "title" and a "body" block.')
                            ->defaultValue('base.html.twig')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('deploy_log')
                            ->info('The log file bin/deploy writes to.')
                            ->defaultValue('%kernel.project_dir%/var/log/deploy.log')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services
            ->set('kuku_sf_system_tools.deploy_log_parser', DeployLogParser::class)
            ->alias(DeployLogParser::class, 'kuku_sf_system_tools.deploy_log_parser');

        $services
            ->set('kuku_sf_system_tools.system_info', SystemInfo::class)
            ->args([
                $config['expose_sf_version'],
                $config['expose_php_version'],
                $config['expose_db_version'],
                $config['expose_last_deploy'],
                // Optional: projects without Doctrine report no database.
                $config['expose_db_version'] ? service('doctrine.dbal.default_connection')->nullOnInvalid() : null,
                service('kuku_sf_system_tools.deploy_log_parser'),
                $config['admin']['deploy_log'],
            ])
            ->alias(SystemInfo::class, 'kuku_sf_system_tools.system_info');

        $services
            ->set('kuku_sf_system_tools.system_info_controller', SFSystemToolsController::class)
            ->args([service('kuku_sf_system_tools.system_info')])
            ->tag('controller.service_arguments');

        if (!$config['admin']['enabled']) {
            return;
        }

        $admin = $config['admin'];

        $services
            ->set('kuku_sf_system_tools.about_info', AboutInfo::class)
            ->args([
                service('kernel'),
                // Optional: projects without Doctrine get no database section.
                service('doctrine.dbal.default_connection')->nullOnInvalid(),
            ]);

        $services
            ->set('kuku_sf_system_tools.about_controller', AboutController::class)
            ->args([
                service('kernel'),
                service('twig'),
                service('security.authorization_checker'),
                $admin['role'],
                $admin['base_template'],
                service('kuku_sf_system_tools.about_info'),
            ])
            ->tag('controller.service_arguments');

        $services
            ->set('kuku_sf_system_tools.logview_controller', LogviewController::class)
            ->args([
                service('kuku_sf_system_tools.deploy_log_parser'),
                service('twig'),
                service('security.authorization_checker'),
                $admin['role'],
                $admin['base_template'],
                $admin['deploy_log'],
            ])
            ->tag('controller.service_arguments');
    }
}
