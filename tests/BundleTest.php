<?php

namespace KuKu\SFSystemToolsBundle\Tests;

use KuKu\SFSystemToolsBundle\KuKuSFSystemToolsBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class BundleTest extends TestCase
{
    public function testTheAdminPagesAreOffByDefault(): void
    {
        $extension = (new KuKuSFSystemToolsBundle())->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $extension->load([[]], $container);

        self::assertTrue($container->hasDefinition('kuku_sf_system_tools.system_info_controller'));
        self::assertFalse($container->hasDefinition('kuku_sf_system_tools.logview_controller'));
        self::assertFalse($container->hasDefinition('kuku_sf_system_tools.about_controller'));
    }
}
