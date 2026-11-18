<?php

namespace KuKu\SFSystemToolsBundle\Tests\App;

use KuKu\SFSystemToolsBundle\KuKuSFSystemToolsBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * A minimal app with the bundle, its admin pages enabled.
 */
class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $deployLog)
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new SecurityBundle();
        yield new KuKuSFSystemToolsBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/kuku_sf_system_tools_test/'.md5($this->deployLog).'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/kuku_sf_system_tools_test/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);
        // Without monolog, Symfony logs to stderr; keep the test output clean.
        $container->services()->set('logger', NullLogger::class);

        $container->extension('twig', [
            'default_path' => __DIR__.'/templates',
        ]);
        $container->extension('security', [
            'providers' => [
                'users' => ['memory' => ['users' => [
                    'admin' => ['password' => 'admin', 'roles' => ['ROLE_ADMIN']],
                    'user' => ['password' => 'user', 'roles' => ['ROLE_USER']],
                ]]],
            ],
            'firewalls' => [
                'main' => ['lazy' => true, 'provider' => 'users', 'http_basic' => null],
            ],
        ]);
        $container->extension('kuku_sf_system_tools', [
            'expose_sf_version' => true,
            'admin' => [
                'base_template' => 'layout.html.twig',
                'deploy_log' => $this->deployLog,
            ],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@KuKuSFSystemToolsBundle/config/routes.yaml')->prefix('/system/info');
        $routes->import('@KuKuSFSystemToolsBundle/config/routes/admin.yaml')->prefix('/system');
    }
}
